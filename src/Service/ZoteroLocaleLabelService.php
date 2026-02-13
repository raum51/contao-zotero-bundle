<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * Lookup-Service für lokalisierte Labels aus tl_zotero_locales.
 *
 * Liefert Item-Typ- und Item-Feld-Labels für eine gegebene Locale mit Fallback auf en_US.
 * Nutzt Contao-Locale-Format (de_AT, en_US) intern.
 */
final class ZoteroLocaleLabelService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Alle Item-Typ-Labels für eine Locale (mit Fallback en_US).
     * Für Options-Listen (Listen-/Such-Modul).
     *
     * @return array<string, string> key => label
     */
    public function getAllItemTypeLabels(string $locale): array
    {
        return $this->getItemTypeLabelsForLocale($locale);
    }

    /**
     * Label für einen Item-Typ ermitteln.
     *
     * Fallback: en-US → Original-Key.
     */
    public function getItemTypeLabel(string $itemType, string $locale): string
    {
        $labels = $this->getItemTypeLabelsForLocale($locale);

        return $labels[$itemType] ?? $itemType;
    }

    /**
     * Label für ein Item-Feld ermitteln.
     *
     * Fallback: en-US → Original-Key.
     */
    public function getItemFieldLabel(string $fieldKey, string $locale): string
    {
        $labels = $this->getItemFieldLabelsForLocale($locale);

        return $labels[$fieldKey] ?? $fieldKey;
    }

    /**
     * Labels für mehrere Item-Feld-Keys ermitteln (für json_dl Template).
     *
     * @param list<string> $keys
     *
     * @return array<string, string> [key => label]
     */
    public function getItemFieldLabelsForKeys(array $keys, string $locale): array
    {
        $labels = $this->getItemFieldLabelsForLocale($locale);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $labels[$key] ?? $key;
        }

        return $result;
    }

    /**
     * Alle Item-Feld-Labels für eine Locale (mit Fallback).
     *
     * @return array<string, string>
     */
    private function getItemFieldLabelsForLocale(string $locale): array
    {
        $row = $this->findLocaleRow($locale);
        if ($row !== null && isset($row['item_fields'])) {
            $decoded = json_decode($row['item_fields'], true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Alle Item-Typ-Labels für eine Locale (mit Fallback).
     *
     * @return array<string, string>
     */
    private function getItemTypeLabelsForLocale(string $locale): array
    {
        $row = $this->findLocaleRow($locale);
        if ($row !== null && isset($row['item_types'])) {
            $decoded = json_decode($row['item_types'], true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Findet die passende Locale-Zeile (exakt oder Fallback en_US).
     *
     * @return array{item_types: string, item_fields: string}|null
     */
    private function findLocaleRow(string $locale): ?array
    {
        $normalized = $this->normalizeRequestLocale($locale);

        $row = $this->connection->fetchAssociative(
            'SELECT item_types, item_fields FROM tl_zotero_locales WHERE locale = ?',
            [$normalized]
        );

        if ($row !== false) {
            return $row;
        }

        if ($normalized !== 'en_US') {
            $row = $this->connection->fetchAssociative(
                'SELECT item_types, item_fields FROM tl_zotero_locales WHERE locale = ?',
                ['en_US']
            );
            if ($row !== false) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Normiert Request-Locale zu Contao-Format (de_AT, en_US).
     * Bindestrich wird zu Unterstrich (falls aus anderer Quelle).
     */
    private function normalizeRequestLocale(string $locale): string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return 'en_US';
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
