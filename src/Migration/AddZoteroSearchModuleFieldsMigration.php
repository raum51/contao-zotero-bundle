<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt die Such-Modul-Felder zu tl_module hinzu.
 *
 * Wird nur ausgeführt, wenn die Spalten noch nicht existieren.
 */
final class AddZoteroSearchModuleFieldsMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Such-Modul-Felder in tl_module';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');
        $columnNames = array_map(static fn ($c) => $c->getName(), $columns);

        return !\in_array('zotero_search_module', $columnNames, true);
    }

    public function run(): MigrationResult
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = array_map(static fn ($c) => $c->getName(), $schemaManager->listTableColumns('tl_module'));

        $newFields = [
            'zotero_search_module' => "ALTER TABLE tl_module ADD zotero_search_module int(10) unsigned NOT NULL default 0",
            'zotero_search_show_author' => "ALTER TABLE tl_module ADD zotero_search_show_author char(1) NOT NULL default '1'",
            'zotero_search_show_year' => "ALTER TABLE tl_module ADD zotero_search_show_year char(1) NOT NULL default '1'",
            'zotero_search_fields' => "ALTER TABLE tl_module ADD zotero_search_fields varchar(64) NOT NULL default 'title,tags,abstract'",
            'zotero_search_token_mode' => "ALTER TABLE tl_module ADD zotero_search_token_mode varchar(4) NOT NULL default 'and'",
            'zotero_search_max_tokens' => "ALTER TABLE tl_module ADD zotero_search_max_tokens varchar(8) NOT NULL default '10'",
            'zotero_search_max_results' => "ALTER TABLE tl_module ADD zotero_search_max_results varchar(8) NOT NULL default '0'",
        ];

        foreach ($newFields as $col => $sql) {
            if (!\in_array($col, $columns, true)) {
                $this->connection->executeStatement($sql);
            }
        }

        return $this->createResult(true, 'Such-Modul-Felder in tl_module ergänzt.');
    }
}
