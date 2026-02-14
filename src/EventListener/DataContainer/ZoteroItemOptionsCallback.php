<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert publizierte Zotero-Items als Options für Einzelauswahl (zotero_item CE, Modus fixed).
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
     * @return array<int, string> id => "Title (Year)"
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, year FROM tl_zotero_item WHERE published = ? ORDER BY title ASC',
            ['1']
        );

        $options = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $title = (string) ($row['title'] ?? '');
            $year = (string) ($row['year'] ?? '');
            $label = $title !== '' ? $title : 'ID ' . $id;
            if ($year !== '') {
                $label .= ' (' . $year . ')';
            }
            $options[$id] = $label;
        }

        return $options;
    }
}
