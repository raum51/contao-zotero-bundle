<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert publizierte Zotero-Items als Options für Einzelauswahl (zotero_item CE, Modus fixed).
 * Format: "Autoren (Jahr): Titel (ID)". Leerer Titel → "Titel unbekannt", kein Jahr → "(Jahr unbekannt)",
 * keine Autoren → "Kein Autor angegeben". Items ohne Autoren werden ans Ende sortiert.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_content ist.
 */
#[AsCallback(table: 'tl_content', target: 'fields.zotero_item_id.options')]
final class ZoteroItemOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => "Autoren (Jahr): Titel (ID)"
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $items = $this->connection->fetchAllAssociative(
            'SELECT id, title, year, json_data FROM tl_zotero_item WHERE published = ? AND trash = ?',
            [1, 0]
        );

        $itemIds = array_map(static fn (array $r) => (int) $r['id'], $items);
        $creatorsByItem = $this->fetchCreatorsByItem($itemIds);

        $withAuthors = [];
        $withoutAuthors = [];
        foreach ($items as $row) {
            $id = (int) $row['id'];
            $title = trim((string) ($row['title'] ?? ''));
            $year = (string) ($row['year'] ?? '');
            $authors = $this->cleanAuthors($creatorsByItem[$id] ?? $this->getAuthorsFromJsonData($row['json_data'] ?? '{}'));

            $displayTitle = $title !== '' ? $title : 'Titel unbekannt';
            $displayAuthors = $authors !== '' ? $authors : 'Kein Autor angegeben';
            $label = $this->formatLabel($displayAuthors, $year, $displayTitle, $id);

            if ($authors !== '') {
                $withAuthors[$id] = $label;
            } else {
                $withoutAuthors[$id] = $label;
            }
        }

        asort($withAuthors, \SORT_NATURAL | \SORT_FLAG_CASE);
        asort($withoutAuthors, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $withAuthors + $withoutAuthors;
    }

    /**
     * @param array<int> $itemIds
     *
     * @return array<int, string> item_id => "Author1; Author2"
     */
    private function fetchCreatorsByItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($itemIds), '?'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ic.item_id, cm.zotero_firstname, cm.zotero_lastname
             FROM tl_zotero_item_creator ic
             JOIN tl_zotero_creator_map cm ON cm.id = ic.creator_map_id
             WHERE ic.item_id IN (' . $placeholders . ')
             ORDER BY ic.item_id, ic.sorting ASC, ic.id ASC',
            $itemIds
        );

        $result = [];
        foreach ($rows as $r) {
            $itemId = (int) $r['item_id'];
            $last = trim((string) ($r['zotero_lastname'] ?? ''));
            $first = trim((string) ($r['zotero_firstname'] ?? ''));
            $part = $last !== '' ? ($first !== '' ? $last . ', ' . $first : $last) : ($first !== '' ? $first : '');
            if ($part !== '' && $this->hasPrintableContent($part)) {
                $result[$itemId] = ($result[$itemId] ?? '') . ($result[$itemId] !== '' ? '; ' : '') . $part;
            }
        }

        return $result;
    }

    /**
     * @return string Autoren-String aus json_data.creators (Fallback)
     */
    private function getAuthorsFromJsonData(string $jsonData): string
    {
        $data = json_decode($jsonData, true);
        if (!\is_array($data)) {
            return '';
        }
        $creators = $data['creators'] ?? [];
        if (!\is_array($creators)) {
            return '';
        }
        $parts = [];
        foreach ($creators as $c) {
            if (!\is_array($c)) {
                continue;
            }
            $name = trim((string) ($c['name'] ?? ''));
            if ($name !== '') {
                $parts[] = $name;
                continue;
            }
            $last = trim((string) ($c['lastName'] ?? ''));
            $first = trim((string) ($c['firstName'] ?? ''));
            $part = $last !== '' ? ($first !== '' ? $last . ', ' . $first : $last) : ($first !== '' ? $first : '');
            if ($part !== '' && $this->hasPrintableContent($part)) {
                $parts[] = $part;
            }
        }

        return $this->cleanAuthors(implode('; ', $parts));
    }

    private function formatLabel(string $authors, string $year, string $title, int $id): string
    {
        $yearPart = $year !== '' ? $year : 'Jahr unbekannt';
        $prefix = $authors;

        if ($prefix !== '') {
            $prefix .= ' (' . $yearPart . '): ';
        } elseif ($year !== '') {
            $prefix = '(' . $year . '): ';
        } else {
            $prefix = '(' . $yearPart . '): ';
        }

        return $prefix . $title . ' (' . $id . ')';
    }

    private function hasPrintableContent(string $s): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $s) === 1;
    }

    private function cleanAuthors(string $authors): string
    {
        return trim(preg_replace('/^[\s;]+|[\s;]+$/u', '', $authors) ?? '');
    }
}
