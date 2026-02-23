<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * tl_zotero_creator_map.member_id: NOT NULL default 0 → NULL default NULL.
 * Bestehende 0-Werte werden zu NULL konvertiert (semantisch „nicht zugeordnet“).
 */
final class ZoteroCreatorMapMemberIdNullMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_zotero_creator_map'])) {
            return false;
        }

        $cols = $this->connection->createSchemaManager()->listTableColumns('tl_zotero_creator_map');
        if (!isset($cols['member_id'])) {
            return false;
        }

        return $cols['member_id']->getNotnull();
    }

    public function run(): MigrationResult
    {
        $messages = [];

        try {
            $this->connection->executeStatement(
                'ALTER TABLE tl_zotero_creator_map MODIFY member_id int(10) unsigned NULL DEFAULT NULL'
            );
            $messages[] = 'tl_zotero_creator_map.member_id: Spalte auf NULL umgestellt.';

            $updated = $this->connection->executeStatement(
                'UPDATE tl_zotero_creator_map SET member_id = NULL WHERE member_id = 0'
            );
            if ($updated > 0) {
                $messages[] = "$updated Einträge: member_id 0 → NULL.";
            }
        } catch (\Throwable $e) {
            return $this->createResult(false, $e->getMessage());
        }

        return $this->createResult(true, implode(' ', $messages));
    }
}
