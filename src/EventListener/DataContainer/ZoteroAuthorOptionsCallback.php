<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert tl_member-Einträge, die publizierten Zotero-Items zugeordnet sind.
 * Format: "Nachname, Vorname (Anzahl publizierter Publikationen)".
 *
 * Verwendet für: zotero_author (Zotero-Liste), zotero_member (Zotero-Creator-Publikationen).
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_content ist.
 */
#[AsCallback(table: 'tl_content', target: 'fields.zotero_author.options')]
#[AsCallback(table: 'tl_content', target: 'fields.zotero_member.options')]
final class ZoteroAuthorOptionsCallback
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
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.id, m.firstname, m.lastname, COUNT(DISTINCT i.id) AS item_count
             FROM tl_member m
             INNER JOIN tl_zotero_creator_map cm ON cm.member_id = m.id
             INNER JOIN tl_zotero_item_creator ic ON ic.creator_map_id = cm.id
             INNER JOIN tl_zotero_item i ON i.id = ic.item_id AND i.published = ? AND i.trash = ?
             GROUP BY m.id
             ORDER BY m.lastname, m.firstname',
            [1, 0]
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

        if ($options === []) {
            return [0 => '– keine Mitglieder mit Publikationen –'];
        }

        return $options;
    }
}
