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
        private readonly ?ApiLogCollector $apiLogCollector = null,
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
        $fullUrl = $url;
        if (!empty($options['query'])) {
            $fullUrl .= '?' . http_build_query($options['query']);
        }
        $options['timeout'] = $options['timeout'] ?? self::REQUEST_TIMEOUT;
        $options['max_duration'] = $options['max_duration'] ?? self::REQUEST_TIMEOUT;
        $requestHeaders = array_merge(
            $options['headers'] ?? [],
            [
                'Zotero-API-Version' => self::API_VERSION_HEADER,
                'Zotero-API-Key' => $apiKey,
            ]
        );
        $options['headers'] = $requestHeaders;

        $attempt = 0;
        $lastResponse = null;

        while (true) {
            $attempt++;
            $this->logger->debug('Zotero API request', [
                'method' => $method,
                'url' => $fullUrl,
                'attempt' => $attempt,
            ]);

            $response = $this->httpClient->request($method, $url, $options);

            try {
                $statusCode = $response->getStatusCode();
            } catch (\Throwable $e) {
                $this->logger->error('Zotero API request failed', [
                    'url' => $fullUrl,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->logger->debug('Zotero API response', [
                'url' => $fullUrl,
                'status' => $statusCode,
            ]);

            if ($this->apiLogCollector?->isEnabled()) {
                $timestamp = date(\DateTimeInterface::ATOM);
                $headersForLog = $requestHeaders;
                if (isset($headersForLog['Zotero-API-Key'])) {
                    $headersForLog['Zotero-API-Key'] = '[REDACTED]';
                }
                $entryIndex = $this->apiLogCollector->recordRequest(
                    $timestamp,
                    $method,
                    $fullUrl,
                    $headersForLog,
                    $statusCode
                );
            }

            // Backoff-Header: API bittet, für N Sekunden keine weiteren Requests zu senden
            $backoff = $response->getHeaders(false)['backoff'] ?? null;
            if ($backoff !== null && isset($backoff[0])) {
                $seconds = (int) $backoff[0];
                $this->logger->info('Zotero API requested backoff', [
                    'url' => $fullUrl,
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
                    'url' => $fullUrl,
                    'retry_after' => $wait,
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                ]);
                if ($attempt < $this->maxRetries) {
                    $body = $response->getContent(false);
                    if (isset($entryIndex)) {
                        $this->apiLogCollector->setResponseBody($entryIndex, $body);
                    }
                    sleep($wait);
                    continue;
                }
                $lastResponse = $response;
                break;
            }

            if (isset($entryIndex) && $this->apiLogCollector !== null) {
                return new LoggingResponseDecorator($response, $this->apiLogCollector, $entryIndex);
            }

            return $response;
        }

        if ($lastResponse !== null) {
            if (isset($entryIndex) && $this->apiLogCollector !== null) {
                return new LoggingResponseDecorator($lastResponse, $this->apiLogCollector, $entryIndex);
            }

            return $lastResponse;
        }

        return $response;
    }

    /**
     * GET-Request mit optionalen Query-Parametern und Optionen (z. B. headers für Cache-Control).
     */
    public function get(string $path, string $apiKey, array $query = [], array $options = []): ResponseInterface
    {
        if ($query !== []) {
            $options['query'] = array_merge($options['query'] ?? [], $query);
        }

        return $this->request('GET', $path, $apiKey, $options);
    }
}
