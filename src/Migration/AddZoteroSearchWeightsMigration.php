<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Erweiterung Zotero-Suche/Filter: Gewichtsfelder, zotero_search_enabled, zotero_search_sort_by_weight,
 * Token-Mode frontend, Filter-Felder Default deaktiviert.
 */
final class AddZoteroSearchWeightsMigration extends AbstractMigration
{
    private const TL_CONTENT_COLUMNS = [
        'zotero_search_enabled' => "char(1) NOT NULL default '1'",
        'zotero_search_sort_by_weight' => "char(1) NOT NULL default '1'",
        'zotero_search_weight_title' => "varchar(8) NOT NULL default '100'",
        'zotero_search_weight_creators' => "varchar(8) NOT NULL default '10'",
        'zotero_search_weight_tags' => "varchar(8) NOT NULL default '10'",
        'zotero_search_weight_publication_title' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_year' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_abstract' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_zotero_key' => "varchar(8) NOT NULL default '1'",
    ];

    private const TL_MODULE_COLUMNS = [
        'zotero_search_enabled' => "char(1) NOT NULL default '1'",
        'zotero_search_sort_by_weight' => "char(1) NOT NULL default '1'",
        'zotero_search_weight_title' => "varchar(8) NOT NULL default '100'",
        'zotero_search_weight_creators' => "varchar(8) NOT NULL default '10'",
        'zotero_search_weight_tags' => "varchar(8) NOT NULL default '10'",
        'zotero_search_weight_publication_title' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_year' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_abstract' => "varchar(8) NOT NULL default '1'",
        'zotero_search_weight_zotero_key' => "varchar(8) NOT NULL default '1'",
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Suche/Filter – Gewichtsfelder, zotero_search_enabled, sort_by_weight';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_content', 'tl_module'])) {
            return false;
        }

        $contentCols = $schemaManager->listTableColumns('tl_content');
        $moduleCols = $schemaManager->listTableColumns('tl_module');

        return !isset($contentCols['zotero_search_weight_title'])
            || !isset($moduleCols['zotero_search_weight_title']);
    }

    public function run(): MigrationResult
    {
        $schemaManager = $this->connection->createSchemaManager();
        $added = [];

        foreach (['tl_content', 'tl_module'] as $table) {
            $columns = $schemaManager->listTableColumns($table);
            $existing = array_map(static fn ($c) => $c->getName(), $columns);
            $defs = $table === 'tl_content' ? self::TL_CONTENT_COLUMNS : self::TL_MODULE_COLUMNS;

            foreach ($defs as $col => $def) {
                if (!\in_array($col, $existing, true)) {
                    $this->connection->executeStatement(
                        sprintf('ALTER TABLE %s ADD %s %s', $table, $col, $def)
                    );
                    $added[] = $table . '.' . $col;
                }
            }
        }

        foreach (['tl_content', 'tl_module'] as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }
            $columns = $schemaManager->listTableColumns($table);
            if (isset($columns['zotero_search_show_author'])) {
                $this->connection->executeStatement(
                    sprintf("ALTER TABLE %s MODIFY zotero_search_show_author char(1) NOT NULL default ''", $table)
                );
            }
            if (isset($columns['zotero_search_show_year'])) {
                $this->connection->executeStatement(
                    sprintf("ALTER TABLE %s MODIFY zotero_search_show_year char(1) NOT NULL default ''", $table)
                );
            }
        }

        $msg = $added !== []
            ? 'Spalten hinzugefügt: ' . implode(', ', $added)
            : 'Keine Änderungen nötig.';

        return $this->createResult(true, $msg);
    }
}
