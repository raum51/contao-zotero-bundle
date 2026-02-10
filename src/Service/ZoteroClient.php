<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP-Client für die Zotero Web API v3.
 *
 * Liegt in src/Service/, weil es ein anwendungsnaher Dienst ist, der API-Aufrufe
 * kapselt (kein reines „Client“-Paket). Der Aufrufer (z. B. SyncService) übergibt
 * den API-Key pro Request, da er pro Library unterschiedlich sein kann.
 *
 * Beachtet Backoff- und Retry-After-Header der API und protokolliert alle
 * Zugriffe sowie Rate-Limit-Ereignisse.
 */
final class ZoteroClient
{
    private const BASE_URL = 'https://api.zotero.org';
    private const API_VERSION_HEADER = '3';
    private const DEFAULT_MAX_RETRIES = 3;
    /** Maximale Wartezeit pro HTTP-Request (Sekunden). Große Libraries/Items können lange Antwortzeiten haben. */
    private const REQUEST_TIMEOUT = 600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl = self::BASE_URL,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
    ) {
    }

    /**
     * Führt einen HTTP-Request gegen die Zotero API aus.
     *
     * @param string $method  GET, POST, etc.
     * @param string $path    Pfad ohne Base-URL, z. B. /users/12345/items
     * @param string $apiKey  Zotero-API-Key (Header Zotero-API-Key)
     * @param array  $options Optionen für HttpClient (query, body, headers, …)
     *
     * @return ResponseInterface Antwort (Inhalt erst nach Bedarf mit getContent() lesen)
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function request(string $method, string $path, string $apiKey, array $options = []): ResponseInterface
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $options['timeout'] = $options['timeout'] ?? self::REQUEST_TIMEOUT;
        $options['max_duration'] = $options['max_duration'] ?? self::REQUEST_TIMEOUT;
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            [
                'Zotero-API-Version' => self::API_VERSION_HEADER,
                'Zotero-API-Key' => $apiKey,
            ]
        );

        $attempt = 0;
        $lastResponse = null;

        while (true) {
            $attempt++;
            $this->logger->debug('Zotero API request', [
                'method' => $method,
                'url' => $url,
                'attempt' => $attempt,
            ]);

            $response = $this->httpClient->request($method, $url, $options);

            try {
                $statusCode = $response->getStatusCode();
            } catch (\Throwable $e) {
                $this->logger->error('Zotero API request failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->debug('Zotero API response', [
                'url' => $url,
                'status' => $statusCode,
            ]);

            // Backoff-Header: API bittet, für N Sekunden keine weiteren Requests zu senden
            $backoff = $response->getHeaders(false)['backoff'] ?? null;
            if ($backoff !== null && isset($backoff[0])) {
                $seconds = (int) $backoff[0];
                $this->logger->info('Zotero API requested backoff', [
                    'url' => $url,
                    'seconds' => $seconds,
                ]);
                if ($seconds > 0) {
                    sleep($seconds);
                }
            }

            // 429 Too Many Requests: warten und ggf. erneut versuchen
            if ($statusCode === 429) {
                $retryAfter = $response->getHeaders(false)['retry-after'] ?? null;
                $wait = $retryAfter !== null && isset($retryAfter[0])
                    ? (int) $retryAfter[0]
                    : 60;
                $this->logger->warning('Zotero API rate limit (429)', [
                    'url' => $url,
                    'retry_after' => $wait,
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                ]);
                if ($attempt < $this->maxRetries) {
                    sleep($wait);
                    continue;
                }
                $lastResponse = $response;
                break;
            }

            return $response;
        }

        if ($lastResponse !== null) {
            return $lastResponse;
        }

        return $response;
    }

    /**
     * GET-Request mit optionalen Query-Parametern.
     */
    public function get(string $path, string $apiKey, array $query = []): ResponseInterface
    {
        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }

        return $this->request('GET', $path, $apiKey, $options);
    }
}
