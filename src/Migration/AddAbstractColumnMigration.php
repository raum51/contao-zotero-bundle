<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt die Spalte abstract zu tl_zotero_item hinzu und überführt bestehende
 * Abstract-Daten aus json_data.abstractNote.
 */
final class AddAbstractColumnMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Spalte abstract in tl_zotero_item + Datentüberführung';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_item'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_zotero_item');

        return !isset($columns['abstract']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            'ALTER TABLE tl_zotero_item ADD abstract mediumtext NULL'
        );

        $affected = $this->connection->executeStatement(
            "UPDATE tl_zotero_item SET abstract = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(json_data, '$.abstractNote')), '')
             WHERE json_data IS NOT NULL AND json_data != '' AND json_data != '{}'"
        );

        return $this->createResult(true, sprintf('Spalte abstract hinzugefügt. %d Item(s) migriert.', $affected));
    }
}
