<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

/**
 * Konvertiert Zotero-Item-Daten in ein Schema.org-konformes JSON-LD-Array.
 *
 * Verwendet für die Detailansicht (Zotero-Einzelelement) zur SEO-Optimierung
 * und strukturierten Daten für Suchmaschinen.
 *
 * Liegt unter Service/, da es eine wiederverwendbare Geschäftslogik ist.
 */
final class ZoteroSchemaOrgService
{
    private const ITEM_TYPE_MAP = [
        'journalArticle' => 'ScholarlyArticle',
        'book' => 'Book',
        'bookSection' => 'ScholarlyArticle',
        'conferencePaper' => 'ScholarlyArticle',
        'report' => 'Report',
        'thesis' => 'Thesis',
        'newspaperArticle' => 'NewsArticle',
    ];

    /**
     * Erzeugt ein Schema.org-konformes Array für ein Zotero-Item.
     *
     * @param array<string, mixed> $itemArray Item-Daten (id, title, year, date, publication_title, item_type, data)
     * @param string              $canonicalUrl Kanonische URL der Detailseite (absolut)
     *
     * @return array<string, mixed>|null Schema.org-Array oder null bei fehlenden Minimaldaten
     */
    public function generateFromItem(array $itemArray, string $canonicalUrl): ?array
    {
        $title = trim((string) ($itemArray['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $itemType = (string) ($itemArray['item_type'] ?? '');
        $data = $itemArray['data'] ?? [];
        $data = \is_array($data) ? $data : [];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => self::ITEM_TYPE_MAP[$itemType] ?? 'CreativeWork',
            'identifier' => '#/schema/zotero/' . ((int) ($itemArray['id'] ?? 0)),
            'name' => $title,
            'url' => $canonicalUrl,
            'mainEntityOfPage' => $canonicalUrl,
        ];

        // Abstract
        $abstract = trim((string) ($data['abstractNote'] ?? ''));
        if ($abstract !== '') {
            $schema['abstract'] = $abstract;
        }

        // Authors (Person-Objekte)
        $authors = $this->buildAuthorsFromCreators($data['creators'] ?? []);
        if ($authors !== []) {
            $schema['author'] = $authors;
        }

        // datePublished (ISO 8601)
        $datePublished = $this->normalizeDatePublished(
            (string) ($itemArray['date'] ?? ''),
            (string) ($itemArray['year'] ?? ''),
            $data
        );
        if ($datePublished !== null) {
            $schema['datePublished'] = $datePublished;
        }

        // Publisher
        $publisher = trim((string) ($data['publisher'] ?? ''));
        if ($publisher !== '') {
            $schema['publisher'] = [
                '@type' => 'Organization',
                'name' => $publisher,
            ];
        }

        // isPartOf (Zeitschrift, Sammelband etc.)
        $publicationTitle = trim((string) ($itemArray['publication_title'] ?? ''));
        if ($publicationTitle !== '' && \in_array($itemType, ['journalArticle', 'bookSection', 'conferencePaper'], true)) {
            $schema['isPartOf'] = [
                '@type' => 'CreativeWork',
                'name' => $publicationTitle,
            ];
        }

        // Keywords (tags)
        $tags = $data['tags'] ?? [];
        if (\is_array($tags)) {
            $keywordStrings = [];
            foreach ($tags as $tag) {
                if (\is_array($tag) && isset($tag['tag'])) {
                    $tagName = trim((string) $tag['tag']);
                    if ($tagName !== '') {
                        $keywordStrings[] = $tagName;
                    }
                } elseif (\is_string($tag) && trim($tag) !== '') {
                    $keywordStrings[] = trim($tag);
                }
            }
            if ($keywordStrings !== []) {
                $schema['keywords'] = implode(', ', $keywordStrings);
            }
        }

        // inLanguage
        $language = trim((string) ($data['language'] ?? ''));
        if ($language !== '') {
            $schema['inLanguage'] = $this->normalizeLanguage($language);
        }

        return $schema;
    }

    /**
     * Baut Person-Objekte aus Zotero creators-Array.
     *
     * @param array<int, mixed> $creators
     *
     * @return list<array<string, string>>
     */
    private function buildAuthorsFromCreators(array $creators): array
    {
        $authors = [];
        foreach ($creators as $c) {
            if (!\is_array($c)) {
                continue;
            }
            $creatorType = (string) ($c['creatorType'] ?? '');
            if ($creatorType !== 'author') {
                continue;
            }
            $person = $this->creatorToPerson($c);
            if ($person !== null) {
                $authors[] = $person;
            }
        }

        return $authors;
    }

    /**
     * @param array<string, mixed> $creator
     *
     * @return array<string, string>|null Person-Schema oder null
     */
    private function creatorToPerson(array $creator): ?array
    {
        $fieldMode = (int) ($creator['fieldMode'] ?? 0);
        if ($fieldMode === 1) {
            $name = trim((string) ($creator['name'] ?? ''));
            if ($name === '') {
                return null;
            }

            return [
                '@type' => 'Person',
                'name' => $name,
            ];
        }

        $firstName = trim((string) ($creator['firstName'] ?? ''));
        $lastName = trim((string) ($creator['lastName'] ?? ''));
        if ($lastName === '' && $firstName === '') {
            return null;
        }

        $person = [
            '@type' => 'Person',
            'givenName' => $firstName,
            'familyName' => $lastName,
        ];
        if ($firstName === '' || $lastName === '') {
            $person['name'] = trim($lastName . ' ' . $firstName);
        }

        return $person;
    }

    private function normalizeDatePublished(string $date, string $year, array $data): ?string
    {
        if ($date !== '') {
            $parsed = $this->parseDateToIso8601($date);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        $dataDate = trim((string) ($data['date'] ?? ''));
        if ($dataDate !== '') {
            $parsed = $this->parseDateToIso8601($dataDate);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
            return $year . '-01-01';
        }

        return null;
    }

    private function parseDateToIso8601(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        // Bereits ISO 8601 (YYYY-MM-DD)?
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return substr($date, 0, 10);
        }
        // Nur Jahr (YYYY)
        if (preg_match('/^\d{4}$/', $date)) {
            return $date . '-01-01';
        }
        // YYYY-MM
        if (preg_match('/^(\d{4})-(\d{2})$/', $date, $m)) {
            return $m[1] . '-' . $m[2] . '-01';
        }
        // DD.MM.YYYY oder ähnlich
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function normalizeLanguage(string $language): string
    {
        $language = trim($language);
        if ($language === '') {
            return 'und';
        }
        // Kurzform (de, en) beibehalten; längere Werte (de-DE) zu BCP 47
        if (strlen($language) <= 3) {
            return strtolower($language);
        }
        $parts = explode('-', $language, 2);
        $main = strtolower($parts[0]);
        if (isset($parts[1]) && $parts[1] !== '') {
            return $main . '-' . strtoupper($parts[1]);
        }

        return $main;
    }
}
