<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Legt die Zotero-Tabellen an (tl_zotero_library, tl_zotero_collection,
 * tl_zotero_collection_item, tl_zotero_item, tl_zotero_creator_map, tl_zotero_item_creator).
 * Reihenfolge berücksichtigt Fremdschlüssel.
 */
final class CreateZoteroTablesMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: Tabellen tl_zotero_* anlegen';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        return !$schemaManager->tablesExist(['tl_zotero_library']);
    }

    public function run(): MigrationResult
    {
        $this->createTableLibrary();
        $this->createTableCreatorMap();
        $this->createTableCollection();
        $this->createTableItem();
        $this->createTableCollectionItem();
        $this->createTableItemCreator();

        return $this->createResult(true, 'Zotero-Tabellen angelegt.');
    }

    private function createTableLibrary(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_library (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                sorting int(10) unsigned NOT NULL DEFAULT 0,
                title varchar(255) NOT NULL DEFAULT '',
                library_id varchar(64) NOT NULL DEFAULT '',
                library_type varchar(16) NOT NULL DEFAULT 'user',
                api_key varchar(64) NOT NULL DEFAULT '',
                citation_style varchar(255) NOT NULL DEFAULT '',
                citation_locale varchar(32) NOT NULL DEFAULT '',
                sync_interval int(10) unsigned NOT NULL DEFAULT 0,
                last_sync_at int(10) unsigned NOT NULL DEFAULT 0,
                last_sync_status varchar(255) NOT NULL DEFAULT '',
                download_attachments char(1) NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }

    private function createTableCreatorMap(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_creator_map (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                zotero_firstname varchar(255) NOT NULL DEFAULT '',
                zotero_lastname varchar(255) NOT NULL DEFAULT '',
                member_id int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY zotero_firstname_zotero_lastname (zotero_firstname, zotero_lastname)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }

    private function createTableCollection(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_collection (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                pid int(10) unsigned NOT NULL DEFAULT 0,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                parent_id int(10) unsigned NOT NULL DEFAULT 0,
                sorting int(10) unsigned NOT NULL DEFAULT 0,
                zotero_key varchar(16) NOT NULL DEFAULT '',
                title varchar(255) NOT NULL DEFAULT '',
                published char(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (id),
                KEY pid (pid),
                KEY parent_id (parent_id),
                KEY zotero_key (zotero_key)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }

    private function createTableItem(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_item (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                pid int(10) unsigned NOT NULL DEFAULT 0,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                zotero_key varchar(16) NOT NULL DEFAULT '',
                zotero_version int(10) unsigned NOT NULL DEFAULT 0,
                title varchar(512) NOT NULL DEFAULT '',
                item_type varchar(32) NOT NULL DEFAULT '',
                year varchar(16) NOT NULL DEFAULT '',
                date varchar(32) NOT NULL DEFAULT '',
                publication_title varchar(512) NOT NULL DEFAULT '',
                cite_content mediumtext NULL,
                bib_content mediumtext NULL,
                json_data mediumtext NULL,
                tags text NULL,
                download_attachments char(1) NOT NULL DEFAULT '',
                published char(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (id),
                KEY pid (pid),
                KEY zotero_key (zotero_key),
                KEY published (published)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }

    private function createTableCollectionItem(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_collection_item (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                pid int(10) unsigned NOT NULL DEFAULT 0,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                collection_id int(10) unsigned NOT NULL DEFAULT 0,
                item_id int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY pid (pid),
                KEY collection_id (collection_id),
                KEY item_id (item_id),
                UNIQUE KEY collection_item (collection_id, item_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }

    private function createTableItemCreator(): void
    {
        $this->connection->executeStatement("
            CREATE TABLE tl_zotero_item_creator (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                pid int(10) unsigned NOT NULL DEFAULT 0,
                tstamp int(10) unsigned NOT NULL DEFAULT 0,
                item_id int(10) unsigned NOT NULL DEFAULT 0,
                creator_map_id int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY pid (pid),
                KEY item_id (item_id),
                KEY creator_map_id (creator_map_id),
                UNIQUE KEY item_creator (item_id, creator_map_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB
        ");
    }
}
