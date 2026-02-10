<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Oberste Collections: parent_id = NULL statt 0 (Vereinbarung: Root = NULL).
 * Passt Spalte an und setzt bestehende parent_id = 0 auf NULL.
 */
final class CollectionParentIdNullMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: tl_zotero_collection.parent_id NULL für Root-Collections';
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_zotero_collection'])) {
            return false;
        }
        $column = $this->connection->createSchemaManager()->listTableColumns('tl_zotero_collection')['parent_id'] ?? null;
        return $column !== null && $column->getNotnull();
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('
            ALTER TABLE tl_zotero_collection
            MODIFY parent_id int(10) unsigned NULL DEFAULT NULL
        ');
        $this->connection->executeStatement('
            UPDATE tl_zotero_collection SET parent_id = NULL WHERE parent_id = 0
        ');
        return $this->createResult(true, 'parent_id erlaubt NULL; bestehende Root-Collections auf NULL gesetzt.');
    }
}
