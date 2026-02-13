<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt Listenmodul-Konfigurationsfelder zu tl_module hinzu.
 *
 * - zotero_list_order: Sortierung (order_author_date, order_year_author, order_title)
 * - zotero_list_group: Gruppierung (library, collection, item_type, year)
 */
final class AddZoteroListConfigMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Listenmodul-Konfiguration (Sortierung, Gruppierung)';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');
        $columnNames = array_map(static fn ($c) => $c->getName(), $columns);

        return !\in_array('zotero_list_order', $columnNames, true)
            || !\in_array('zotero_list_group', $columnNames, true);
    }

    public function run(): MigrationResult
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = array_map(static fn ($c) => $c->getName(), $schemaManager->listTableColumns('tl_module'));

        $newFields = [
            'zotero_list_order' => "ALTER TABLE tl_module ADD zotero_list_order varchar(32) NOT NULL default 'order_title'",
            'zotero_list_group' => "ALTER TABLE tl_module ADD zotero_list_group varchar(32) NOT NULL default ''",
        ];

        foreach ($newFields as $col => $sql) {
            if (!\in_array($col, $columns, true)) {
                $this->connection->executeStatement($sql);
            }
        }

        return $this->createResult(true, 'Listenmodul-Konfiguration in tl_module ergänzt.');
    }
}
