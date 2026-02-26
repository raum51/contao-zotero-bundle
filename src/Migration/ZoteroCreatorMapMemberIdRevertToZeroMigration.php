<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * tl_zotero_creator_map.member_id: NULL → NOT NULL default 0 (Contao-typisch).
 * Bestehende NULL-Werte werden zu 0 konvertiert.
 */
final class ZoteroCreatorMapMemberIdRevertToZeroMigration extends AbstractMigration
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

        return !$cols['member_id']->getNotnull();
    }

    public function run(): MigrationResult
    {
        $messages = [];

        try {
            $updated = $this->connection->executeStatement(
                'UPDATE tl_zotero_creator_map SET member_id = 0 WHERE member_id IS NULL'
            );
            if ($updated > 0) {
                $messages[] = "$updated Einträge: member_id NULL → 0.";
            }

            $this->connection->executeStatement(
                'ALTER TABLE tl_zotero_creator_map MODIFY member_id int(10) unsigned NOT NULL default 0'
            );
            $messages[] = 'tl_zotero_creator_map.member_id: Spalte auf NOT NULL default 0 umgestellt.';
        } catch (\Throwable $e) {
            return $this->createResult(false, $e->getMessage());
        }

        return $this->createResult(true, implode(' ', $messages));
    }
}
