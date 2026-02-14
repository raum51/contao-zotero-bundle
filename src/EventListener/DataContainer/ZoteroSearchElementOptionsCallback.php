<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert Zotero-Such-Inhaltselemente (type=zotero_search) als Optionen.
 * Für das Listen-CE-Feld zotero_search_element – ermöglicht Verknüpfung Liste → Suche.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_content ist.
 */
#[AsCallback(table: 'tl_content', target: 'fields.zotero_search_element.options')]
final class ZoteroSearchElementOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => "Titel (ID)"
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title FROM tl_content
             WHERE type = 'zotero_search'
             ORDER BY id DESC"
        );

        $options = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $title = trim((string) ($row['title'] ?? ''));
            $label = $title !== '' ? $title . ' (' . $id . ')' : 'CE ' . $id;
            $options[$id] = $label;
        }

        return $options;
    }
}
