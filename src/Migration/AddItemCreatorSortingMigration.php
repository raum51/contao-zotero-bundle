<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt sorting zu tl_zotero_item_creator hinzu.
 *
 * Bewahrt die Reihenfolge der Autoren (Zotero creators-Array) bei Sync und Frontend.
 */
final class AddItemCreatorSortingMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Creator-Reihenfolge (sorting) in tl_zotero_item_creator';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_item_creator'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_zotero_item_creator');

        return !\in_array('sorting', array_map(static fn ($c) => $c->getName(), $columns), true);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            'ALTER TABLE tl_zotero_item_creator ADD sorting int(10) unsigned NOT NULL default 0'
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, item_id FROM tl_zotero_item_creator ORDER BY item_id, id ASC'
        );
        $lastItemId = null;
        $sorting = 0;
        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            if ($itemId !== $lastItemId) {
                $lastItemId = $itemId;
                $sorting = 0;
            }
            $this->connection->update('tl_zotero_item_creator', ['sorting' => $sorting], ['id' => $row['id']]);
            $sorting++;
        }

        return $this->createResult(true, 'sorting in tl_zotero_item_creator ergänzt und bestehende Einträge aktualisiert.');
    }
}
