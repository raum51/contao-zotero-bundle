<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt Item-Typ-Felder zu tl_module hinzu.
 *
 * - zotero_item_types: Listenmodul – Auswahl der anzuzeigenden Item-Typen
 * - zotero_search_show_item_type: Suchmodul – Filter Item-Typ anzeigen
 */
final class AddZoteroItemTypesMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Item-Typ-Felder in tl_module';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');
        $columnNames = array_map(static fn ($c) => $c->getName(), $columns);

        return !\in_array('zotero_item_types', $columnNames, true)
            || !\in_array('zotero_search_show_item_type', $columnNames, true);
    }

    public function run(): MigrationResult
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = array_map(static fn ($c) => $c->getName(), $schemaManager->listTableColumns('tl_module'));

        $newFields = [
            'zotero_item_types' => 'ALTER TABLE tl_module ADD zotero_item_types blob NULL',
            'zotero_search_show_item_type' => "ALTER TABLE tl_module ADD zotero_search_show_item_type char(1) NOT NULL default ''",
        ];

        foreach ($newFields as $col => $sql) {
            if (!\in_array($col, $columns, true)) {
                $this->connection->executeStatement($sql);
            }
        }

        return $this->createResult(true, 'Item-Typ-Felder in tl_module ergänzt.');
    }
}
