<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt die Unique-Indexe wieder hinzu, die von Contao beim Schema-Abgleich
 * entfernt werden (weil composite keys in config.sql.keys nicht abgebildet werden).
 */
final class AddZoteroUniqueIndexesMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: Unique-Indexe (collection_item, creator_map, item_creator) anlegen';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_collection_item', 'tl_zotero_creator_map', 'tl_zotero_item_creator'])) {
            return false;
        }
        $collectionItemIndexes = $schemaManager->listTableIndexes('tl_zotero_collection_item');
        return !isset($collectionItemIndexes['idx_collection_item']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('ALTER TABLE tl_zotero_collection_item ADD UNIQUE KEY idx_collection_item (collection_id, item_id)');
        $this->connection->executeStatement('ALTER TABLE tl_zotero_creator_map ADD UNIQUE KEY zotero_firstname_zotero_lastname (zotero_firstname, zotero_lastname)');
        $this->connection->executeStatement('ALTER TABLE tl_zotero_item_creator ADD UNIQUE KEY idx_item_creator (item_id, creator_map_id)');
        return $this->createResult(true, 'Unique-Indexe angelegt.');
    }
}
