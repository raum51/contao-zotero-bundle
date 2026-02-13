<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt das Feld zotero_list_sort_direction_date zu tl_module hinzu.
 *
 * Steuert die Sortierrichtung für Datum/Jahr bei Sortieroptionen mit Datum
 * sowie bei Gruppierung nach Jahr (asc = älteste zuerst, desc = neueste zuerst).
 */
final class AddZoteroListSortDirectionDateMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Listenmodul Sortierrichtung Datum/Jahr';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');
        $columnNames = array_map(static fn ($c) => $c->getName(), $columns);

        return !\in_array('zotero_list_sort_direction_date', $columnNames, true);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            "ALTER TABLE tl_module ADD zotero_list_sort_direction_date varchar(4) NOT NULL default 'desc'"
        );

        return $this->createResult(true, 'zotero_list_sort_direction_date in tl_module ergänzt.');
    }
}
