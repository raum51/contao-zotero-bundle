<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lädt lokalisierte Zotero-Schema-Daten (Item-Typen, Item-Felder) von der API
 * und speichert sie pro Locale in tl_zotero_locales.
 *
 * Kein API-Key nötig – Schema-Endpoints sind öffentlich.
 * Locales: en_US (Fallback), de_DE, plus citation_locale aller Libraries und Sprache aller Website-Roots.
 * Intern Contao-Format (de_AT), bei API-Aufrufen Konvertierung zu BCP-47 (de-AT).
 */
final class ZoteroLocaleService
{
    private const API_BASE = 'https://api.zotero.org';
    private const ITEM_TYPES_PATH = 'itemTypes';
    private const ITEM_FIELDS_PATH = 'itemFields';

    /** Strukturelle Keys in item.data, die nicht von /itemFields kommen. Contao-Locale-Format. */
    private const STRUCTURAL_FIELD_LABELS = [
        'creators' => ['de_DE' => 'Autoren', 'de_AT' => 'Autoren', 'en_US' => 'Creators'],
        'tags' => ['de_DE' => 'Schlagwörter', 'de_AT' => 'Schlagwörter', 'en_US' => 'Tags'],
        'collections' => ['de_DE' => 'Sammlungen', 'de_AT' => 'Sammlungen', 'en_US' => 'Collections'],
        'relations' => ['de_DE' => 'Beziehungen', 'de_AT' => 'Beziehungen', 'en_US' => 'Relations'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ermittelt die zu ladenden Locales: en-US, de-DE, plus Libraries und Roots.
     *
     * @return list<string>
     */
    public function getLocalesToFetch(): array
    {
        $locales = ['en_US', 'de_DE'];

        $libraryLocales = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT citation_locale FROM tl_zotero_library WHERE citation_locale != \'\' AND citation_locale IS NOT NULL'
        );
        foreach ($libraryLocales as $loc) {
            $normalized = $this->normalizeLocale((string) $loc);
            if ($normalized !== '' && !\in_array($normalized, $locales, true)) {
                $locales[] = $normalized;
            }
        }

        $rootLocales = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT language FROM tl_page WHERE type = 'root' AND language != ''"
        );
        foreach ($rootLocales as $loc) {
            $normalized = $this->normalizeLocale((string) $loc);
            if ($normalized !== '' && !\in_array($normalized, $locales, true)) {
                $locales[] = $normalized;
            }
        }

        return $locales;
    }

    /**
     * Holt Item-Typen und Item-Felder pro Locale und synchronisiert tl_zotero_locales.
     *
     * @return array{locales_created: int, locales_updated: int, locales_deleted: int, errors: list<string>}
     */
    public function fetchAndStore(): array
    {
        $result = [
            'locales_created' => 0,
            'locales_updated' => 0,
            'locales_deleted' => 0,
            'errors' => [],
        ];

        $locales = $this->getLocalesToFetch();

        foreach ($locales as $locale) {
            try {
                $apiLocale = $this->toApiLocale($locale);
                $itemTypes = $this->fetchItemTypes($apiLocale);
                $itemFields = $this->fetchItemFields($apiLocale);
                $itemFields = $this->mergeStructuralFieldLabels($itemFields, $locale);

                $existing = $this->connection->fetchOne('SELECT id FROM tl_zotero_locales WHERE locale = ?', [$locale]);

                $itemTypesJson = json_encode($itemTypes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
                $itemFieldsJson = json_encode($itemFields, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

                if ($existing !== false) {
                    $this->connection->update(
                        'tl_zotero_locales',
                        [
                            'item_types' => $itemTypesJson,
                            'item_fields' => $itemFieldsJson,
                            'tstamp' => time(),
                        ],
                        ['locale' => $locale]
                    );
                    $result['locales_updated']++;
                    $this->logger->info('Zotero-Locale aktualisiert', ['locale' => $locale]);
                } else {
                    $this->connection->insert('tl_zotero_locales', [
                        'tstamp' => time(),
                        'locale' => $locale,
                        'item_types' => $itemTypesJson,
                        'item_fields' => $itemFieldsJson,
                    ]);
                    $result['locales_created']++;
                    $this->logger->info('Zotero-Locale angelegt', ['locale' => $locale]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Zotero-Locale fehlgeschlagen', ['locale' => $locale, 'error' => $e->getMessage()]);
                $result['errors'][] = $locale . ': ' . $e->getMessage();
            }
        }

        $existingLocales = $this->connection->fetchFirstColumn('SELECT locale FROM tl_zotero_locales');
        foreach ($existingLocales as $ex) {
            if (!\in_array($ex, $locales, true)) {
                $this->connection->delete('tl_zotero_locales', ['locale' => $ex]);
                $result['locales_deleted']++;
                $this->logger->info('Zotero-Locale gelöscht (nicht mehr benötigt)', ['locale' => $ex]);
            }
        }

        $this->logger->info('Zotero-Locales Sync abgeschlossen', [
            'created' => $result['locales_created'],
            'updated' => $result['locales_updated'],
            'deleted' => $result['locales_deleted'],
        ]);

        return $result;
    }

    /**
     * Ruft GET /itemTypes mit Locale ab.
     *
     * @return array<string, string> [itemType => localized]
     */
    private function fetchItemTypes(string $locale): array
    {
        $url = self::API_BASE . '/' . self::ITEM_TYPES_PATH . '?locale=' . $locale;
        $this->logger->debug('Zotero Item-Typen: API-Abruf', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Zotero-API-Version' => '3',
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return [];
        }

        $map = [];
        foreach ($data as $item) {
            $key = $item['itemType'] ?? '';
            $map[$key] = $item['localized'] ?? $key;
        }

        return $map;
    }

    /**
     * Ruft GET /itemFields mit Locale ab.
     *
     * @return array<string, string> [field => localized]
     */
    private function fetchItemFields(string $locale): array
    {
        $url = self::API_BASE . '/' . self::ITEM_FIELDS_PATH . '?locale=' . $locale;
        $this->logger->debug('Zotero Item-Felder: API-Abruf', ['url' => $url]);

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Zotero-API-Version' => '3',
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return [];
        }

        $map = [];
        foreach ($data as $item) {
            $key = $item['field'] ?? '';
            $map[$key] = $item['localized'] ?? $key;
        }

        return $map;
    }

    /**
     * Ergänzt item_fields um die strukturellen Keys (creators, tags, collections, relations).
     *
     * @param array<string, string> $itemFields
     *
     * @return array<string, string>
     */
    private function mergeStructuralFieldLabels(array $itemFields, string $locale): array
    {
        $fallback = null;
        if (str_contains($locale, '_')) {
            $parts = explode('_', $locale);
            $fallback = $parts[0] . '_' . strtoupper($parts[0]);
        }
        foreach (self::STRUCTURAL_FIELD_LABELS as $key => $labels) {
            if (!isset($itemFields[$key])) {
                $label = $labels[$locale] ?? ($fallback !== null ? ($labels[$fallback] ?? null) : null);
                $itemFields[$key] = $label ?? $labels['en_US'] ?? $key;
            }
        }

        return $itemFields;
    }

    /**
     * Konvertiert Contao-Locale (de_AT) zu Zotero-API-Format (de-AT).
     */
    private function toApiLocale(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /**
     * Normalisiert Locale zu Contao-Format (de_AT, en_US).
     * Ausgabe für Speicherung und interne Nutzung.
     */
    private function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return '';
        }
        $locale = str_replace('-', '_', $locale);
        if (str_contains($locale, '_')) {
            return $locale;
        }
        $map = [
            'de' => 'de_DE',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'it' => 'it_IT',
            'es' => 'es_ES',
        ];

        return $map[$locale] ?? $locale;
    }
}
