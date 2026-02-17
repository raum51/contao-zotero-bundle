<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert tl_member-Einträge, die mindestens ein publiziertes Item in der
 * bearbeiteten Library haben. Format: "Nachname, Vorname (Anzahl)".
 *
 * Verwendet für: sitemap_authors in tl_zotero_library (Sitemap-Filter).
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_zotero_library ist.
 */
#[AsCallback(table: 'tl_zotero_library', target: 'fields.sitemap_authors.options')]
final class ZoteroSitemapAuthorsOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> member_id => "Nachname, Vorname (count)"
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        if ($dc === null || !$dc->id) {
            return [];
        }

        $libraryId = (int) $dc->id;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.id, m.firstname, m.lastname, COUNT(DISTINCT i.id) AS item_count
             FROM tl_member m
             INNER JOIN tl_zotero_creator_map cm ON cm.member_id = m.id
             INNER JOIN tl_zotero_item_creator ic ON ic.creator_map_id = cm.id
             INNER JOIN tl_zotero_item i ON i.id = ic.item_id AND i.pid = ? AND i.published = ?
             GROUP BY m.id
             ORDER BY m.lastname, m.firstname',
            [$libraryId, '1']
        );

        $options = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $last = trim((string) ($row['lastname'] ?? ''));
            $first = trim((string) ($row['firstname'] ?? ''));
            $count = (int) ($row['item_count'] ?? 0);
            $name = $last !== '' ? ($first !== '' ? $last . ', ' . $first : $last) : ($first !== '' ? $first : 'ID ' . $id);
            $options[$id] = $name . ' (' . $count . ')';
        }

        return $options;
    }
}
