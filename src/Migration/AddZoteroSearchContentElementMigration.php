<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt die Spalten für das Zotero-Such-Inhaltselement (zotero_search) zu tl_content hinzu.
 * Ergänzt außerdem zotero_search_element für das Listen-CE.
 *
 * Felder: zotero_search_element, zotero_list_page, zotero_search_show_author,
 * zotero_search_show_year, zotero_search_show_item_type, zotero_search_fields,
 * zotero_search_token_mode, zotero_search_max_tokens, zotero_search_max_results.
 */
final class AddZoteroSearchContentElementMigration extends AbstractMigration
{
    private const COLUMNS = [
        'zotero_search_element' => "int(10) unsigned NOT NULL default 0",
        'zotero_list_page' => "int(10) unsigned NOT NULL default 0",
        'zotero_search_show_author' => "char(1) NOT NULL default '1'",
        'zotero_search_show_year' => "char(1) NOT NULL default '1'",
        'zotero_search_show_item_type' => "char(1) NOT NULL default ''",
        'zotero_search_fields' => "varchar(64) NOT NULL default 'title,tags,abstract'",
        'zotero_search_token_mode' => "varchar(4) NOT NULL default 'and'",
        'zotero_search_max_tokens' => "varchar(8) NOT NULL default '10'",
        'zotero_search_max_results' => "varchar(8) NOT NULL default '0'",
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Such-Inhaltselement (zotero_search) – tl_content-Spalten';
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
