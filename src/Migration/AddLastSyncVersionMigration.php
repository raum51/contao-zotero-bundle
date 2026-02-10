<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt last_sync_version zu tl_zotero_library hinzu (für inkrementellen Sync).
 * Liegt in Migration/, damit Contao die Migration beim Aufruf von contao:migrate erkennt.
 */
final class AddLastSyncVersionMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: last_sync_version in tl_zotero_library';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_library'])) {
            return false;
        }
        $columns = $schemaManager->listTableColumns('tl_zotero_library');
        foreach (array_keys($columns) as $name) {
            if (strtolower($name) === 'last_sync_version') {
                return false;
            }
        }

        return true;
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('
            ALTER TABLE tl_zotero_library
            ADD COLUMN last_sync_version int(10) unsigned NOT NULL DEFAULT 0
            AFTER last_sync_status
        ');

        return $this->createResult(true, 'Spalte last_sync_version hinzugefügt.');
    }
}
