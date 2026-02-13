<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Entfernt die Tabelle tl_zotero_itemtypes (ersetzt durch tl_zotero_locales).
 */
final class DropItemTypesTableMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Tabelle tl_zotero_itemtypes entfernen (ersetzt durch tl_zotero_locales)';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist(['tl_zotero_itemtypes']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS tl_zotero_itemtypes');

        return $this->createResult(true, 'Tabelle tl_zotero_itemtypes entfernt.');
    }
}
