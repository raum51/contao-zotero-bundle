<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroSyncService;

/**
 * Migriert Tags in tl_zotero_item von JSON [{"tag":"x"},...] ins neue Format ", ".
 *
 * Liegt in src/Migration/, da Contao Migrationen über den contao.migration Tag
 * automatisch erkannt werden.
 */
final class TagsFormatMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: Tags von JSON auf kommasepariertes Format migrieren';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_item'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_zotero_item');
        if (!isset($columns['tags'])) {
            return false;
        }

        $count = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_zotero_item WHERE tags != '' AND tags IS NOT NULL AND tags LIKE '[%'"
        );

        return $count > 0;
    }

    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, tags FROM tl_zotero_item WHERE tags != '' AND tags IS NOT NULL AND tags LIKE '[%'"
        );

        $updated = 0;
        foreach ($rows as $row) {
            $tagsJson = (string) ($row['tags'] ?? '');
            if ($tagsJson === '') {
                continue;
            }

            $decoded = json_decode($tagsJson, true);
            if (!\is_array($decoded)) {
                continue;
            }

            $newValue = ZoteroSyncService::convertZoteroTagsToStorageFormat($decoded);
            $this->connection->update(
                'tl_zotero_item',
                ['tags' => $newValue ?? ''],
                ['id' => (int) $row['id']]
            );
            $updated++;
        }

        return $this->createResult(
            true,
            sprintf('%d Zotero-Item(s) auf neues Tags-Format migriert.', $updated)
        );
    }
}
