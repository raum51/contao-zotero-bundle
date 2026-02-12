<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

/**
 * Sammelt API-Aufrufe für Protokollierung (--log-api).
 *
 * Liegt in src/Service/, da es die Zotero-Client-Layer unterstützt. Wird pro Sync-Lauf
 * aktiviert und am Ende als JSON-Datei geschrieben.
 */
final class ApiLogCollector
{
    private ?string $logPath = null;

    /** @var array<string, mixed> Metadaten (command, timestamp, options) */
    private array $metadata = [];

    /** @var list<array<string, mixed>> Einträge pro API-Aufruf */
    private array $entries = [];

    public function enable(string $path, array $metadata = []): void
    {
        $this->logPath = $path;
        $this->metadata = $metadata;
        $this->entries = [];
    }

    public function disable(): void
    {
        $this->logPath = null;
        $this->metadata = [];
        $this->entries = [];
    }

    public function isEnabled(): bool
    {
        return $this->logPath !== null;
    }

    /**
     * Registriert einen API-Aufruf (ohne Response-Body).
     * Gibt den Index zurück, um später den Body zu setzen.
     */
    public function recordRequest(string $timestamp, string $method, string $url, array $requestHeaders, int $responseCode): int
    {
        $index = \count($this->entries);
        $this->entries[] = [
            'timestamp' => $timestamp,
            'request' => [
                'method' => $method,
                'url' => $url,
                'headers' => $requestHeaders,
            ],
            'response_code' => $responseCode,
            'response' => null,
        ];

        return $index;
    }

    /**
     * Setzt den Response-Body für einen zuvor registrierten Aufruf.
     * Der Body wird als decoded JSON gespeichert, falls möglich; sonst als String.
     */
    public function setResponseBody(int $index, string $body): void
    {
        if (!isset($this->entries[$index])) {
            return;
        }
        $decoded = json_decode($body, true);
        $this->entries[$index]['response'] = \is_array($decoded) ? $decoded : $body;
    }

    /**
     * Schreibt die gesammelten Daten als JSON in die Zieldatei.
     */
    public function flush(): void
    {
        if ($this->logPath === null) {
            $this->disable();

            return;
        }

        $entries = $this->entries;
        usort($entries, static fn (array $a, array $b): int => strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? ''));

        $payload = [
            'metadata' => array_merge(
                [
                    'timestamp' => $this->metadata['timestamp'] ?? date(\DateTimeInterface::ATOM),
                    'command' => $this->metadata['command'] ?? 'contao:zotero:sync',
                ],
                $this->metadata
            ),
            'api_calls' => $entries,
        ];

        $dir = \dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        file_put_contents($this->logPath, $json);

        $this->disable();
    }
}
