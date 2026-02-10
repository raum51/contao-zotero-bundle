<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt published zu tl_zotero_library hinzu (für Frontend-Filterung).
 */
final class AddPublishedFieldMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: published in tl_zotero_library';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_library'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_zotero_library');
        foreach ($columns as $column) {
            if (strtolower($column->getName()) === 'published') {
                return false;
            }
        }

        return true;
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("
            ALTER TABLE tl_zotero_library
            ADD COLUMN published char(1) NOT NULL DEFAULT '1'
            AFTER download_attachments
        ");

        return $this->createResult(true, 'Spalte published hinzugefügt.');
    }
}
