<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert alle Zotero-Such-Module als Optionen für das Listenmodul-Feld zotero_search_module.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA options_callback für tl_module ist.
 * Ermöglicht die Verknüpfung Listen-Modul → Such-Modul für Library-Schnittmenge und Such-Konfiguration.
 */
#[AsCallback(table: 'tl_module', target: 'fields.zotero_search_module.options')]
final class ZoteroSearchModuleOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => name
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, name FROM tl_module WHERE type = 'zotero_search' ORDER BY name"
        );

        $options = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? 'ID ' . $row['id'];
            $options[(int) $row['id']] = $name;
        }

        return $options;
    }
}
