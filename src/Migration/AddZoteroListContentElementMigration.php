<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt die Spalten für das Zotero-Listen-Inhaltselement (zotero_list) zu tl_content hinzu.
 *
 * Felder: zotero_collections, zotero_item_types, zotero_author, zotero_reader_element,
 * zotero_search_module, numberOfItems, perPage, zotero_list_order,
 * zotero_list_sort_direction_date, zotero_list_group.
 */
final class AddZoteroListContentElementMigration extends AbstractMigration
{
    private const COLUMNS = [
        'zotero_collections' => "blob NULL",
        'zotero_item_types' => "blob NULL",
        'zotero_author' => "int(10) unsigned NOT NULL default 0",
        'zotero_reader_element' => "int(10) unsigned NOT NULL default 0",
        'zotero_search_module' => "int(10) unsigned NOT NULL default 0",
        'numberOfItems' => "varchar(8) NOT NULL default '0'",
        'perPage' => "varchar(8) NOT NULL default '0'",
        'zotero_list_order' => "varchar(32) NOT NULL default 'order_title'",
        'zotero_list_sort_direction_date' => "varchar(4) NOT NULL default 'desc'",
        'zotero_list_group' => "varchar(32) NOT NULL default ''",
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Listen-Inhaltselement (zotero_list) – tl_content-Spalten';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_content'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_content');
        $existing = array_map(static fn ($c) => $c->getName(), $columns);

        foreach (array_keys(self::COLUMNS) as $col) {
            if (!\in_array($col, $existing, true)) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('tl_content');
        $existing = array_map(static fn ($c) => $c->getName(), $columns);

        $added = [];
        foreach (self::COLUMNS as $col => $def) {
            if (!\in_array($col, $existing, true)) {
                $this->connection->executeStatement(
                    sprintf('ALTER TABLE tl_content ADD %s %s', $col, $def)
                );
                $added[] = $col;
            }
        }

        $msg = $added !== []
            ? 'Spalten hinzugefügt: ' . implode(', ', $added)
            : 'Keine Änderungen nötig.';

        return $this->createResult(true, $msg);
    }
}
