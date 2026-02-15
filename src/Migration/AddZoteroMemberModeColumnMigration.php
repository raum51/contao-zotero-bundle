<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt zotero_member_mode zu tl_content hinzu.
 *
 * Wird für das CE „Zotero-Creator-Publikationen“ (zotero_creator_items) benötigt.
 */
final class AddZoteroMemberModeColumnMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: zotero_member_mode in tl_content';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_content'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_content');

        return !\in_array('zotero_member_mode', array_map(static fn ($c) => $c->getName(), $columns), true);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            "ALTER TABLE tl_content ADD zotero_member_mode varchar(16) NOT NULL default 'fixed'"
        );

        return $this->createResult(true, 'zotero_member_mode in tl_content ergänzt.');
    }
}
