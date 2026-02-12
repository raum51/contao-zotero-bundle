<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Migriert tl_module.zotero_library (int) nach zotero_libraries (blob).
 *
 * Überführt bestehende Einzelauswahl in Mehrfachauswahl (serialisierte ID-Liste).
 */
final class ConvertZoteroLibraryToLibrariesMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: zotero_library → zotero_libraries (tl_module)';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');
        $hasOld = isset($columns['zotero_library']);
        $hasNew = isset($columns['zotero_libraries']);

        return $hasOld && !$hasNew;
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            'ALTER TABLE tl_module ADD zotero_libraries blob NULL'
        );

        // Pro Zeile: zotero_library-Wert als serialisiertes Array [id]
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, zotero_library FROM tl_module WHERE zotero_library > 0'
        );
        foreach ($rows as $row) {
            $libraryId = (int) $row['zotero_library'];
            $this->connection->update(
                'tl_module',
                ['zotero_libraries' => serialize([$libraryId])],
                ['id' => $row['id']]
            );
        }

        $this->connection->executeStatement(
            'ALTER TABLE tl_module DROP COLUMN zotero_library'
        );

        $count = \count($rows);

        return $this->createResult(true, sprintf('%d Modul(e) migriert (zotero_library → zotero_libraries).', $count));
    }
}
