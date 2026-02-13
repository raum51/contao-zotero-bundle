<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Erstellt die Tabelle tl_zotero_locales für lokalisierte Zotero-Schema-Daten.
 *
 * Befüllung per Command: contao:zotero:fetch-locales
 */
final class CreateLocalesTableMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Tabelle tl_zotero_locales anlegen';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return !$schemaManager->tablesExist(['tl_zotero_locales']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE tl_zotero_locales (
                id int(10) unsigned NOT NULL auto_increment,
                tstamp int(10) unsigned NOT NULL default 0,
                locale varchar(16) NOT NULL default '',
                item_types mediumtext NULL,
                item_fields mediumtext NULL,
                PRIMARY KEY (id),
                UNIQUE KEY locale (locale)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        return $this->createResult(true, 'Tabelle tl_zotero_locales angelegt.');
    }
}
