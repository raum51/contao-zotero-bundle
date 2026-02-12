<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert alle Zotero-Reader-Module als Optionen für das Listenmodul-Feld zotero_reader_module.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA options_callback für tl_module ist.
 * Zeigt alle Module vom Typ zotero_reader an (unabhängig von der gewählten Library).
 */
class ZoteroReaderModuleOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => name/title
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, name FROM tl_module WHERE type = 'zotero_reader' ORDER BY name"
        );

        $options = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? 'ID ' . $row['id'];
            $options[(int) $row['id']] = $name;
        }

        return $options;
    }
}
