<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt trash-Feld für tl_zotero_item und tl_zotero_item_attachment hinzu.
 * Konvertiert boolean-ähnliche char(1)-Felder in Contao-konforme tinyint(1).
 */
final class ZoteroTrashAndBooleanMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_zotero_item', 'tl_zotero_item_attachment', 'tl_zotero_library', 'tl_zotero_collection'])) {
            return false;
        }

        $itemCols = $schemaManager->listTableColumns('tl_zotero_item');
        $attachCols = $schemaManager->listTableColumns('tl_zotero_item_attachment');
        $libraryCols = $schemaManager->listTableColumns('tl_zotero_library');
        $collectionCols = $schemaManager->listTableColumns('tl_zotero_collection');

        // Migration läuft, wenn trash fehlt oder boolean-Felder noch char(1) sind
        $needsTrashItem = !isset($itemCols['trash']);
        $needsTrashAttachment = !isset($attachCols['trash']);
        $anyBooleanNeedsConversion = $this->isCharColumn($itemCols['published'] ?? null)
            || $this->isCharColumn($itemCols['download_attachments'] ?? null)
            || $this->isCharColumn($attachCols['published'] ?? null)
            || $this->isCharColumn($libraryCols['published'] ?? null)
            || $this->isCharColumn($libraryCols['download_attachments'] ?? null)
            || $this->isCharColumn($libraryCols['include_in_sitemap'] ?? null)
            || $this->isCharColumn($collectionCols['published'] ?? null);

        $contentZoteroIsChar = false;
        if ($schemaManager->tablesExist(['tl_content'])) {
            $contentCols = $schemaManager->listTableColumns('tl_content');
            $contentZoteroIsChar = $this->isCharColumn($contentCols['zotero_download_attachments'] ?? null);
        }

        return $needsTrashItem || $needsTrashAttachment || $anyBooleanNeedsConversion || $contentZoteroIsChar;
    }

    public function run(): MigrationResult
    {
        $messages = [];

        // 1) trash in tl_zotero_item
        if ($this->columnMissing('tl_zotero_item', 'trash')) {
            $this->connection->executeStatement(
                'ALTER TABLE tl_zotero_item ADD trash TINYINT(1) NOT NULL DEFAULT 0'
            );
            $messages[] = 'tl_zotero_item: trash-Spalte hinzugefügt.';
        }

        // 2) trash in tl_zotero_item_attachment
        if ($this->columnMissing('tl_zotero_item_attachment', 'trash')) {
            $this->connection->executeStatement(
                'ALTER TABLE tl_zotero_item_attachment ADD trash TINYINT(1) NOT NULL DEFAULT 0'
            );
            $messages[] = 'tl_zotero_item_attachment: trash-Spalte hinzugefügt.';
        }

        // 3) Bestehende Daten: trash aus json_data setzen wo Zotero deleted=1
        if ($this->columnExists('tl_zotero_item', 'trash') && $this->columnExists('tl_zotero_item', 'json_data')) {
            $updated = $this->connection->executeStatement(
                "UPDATE tl_zotero_item SET trash = 1 WHERE json_data LIKE '%\"deleted\":1%' OR json_data LIKE '%\"deleted\": 1%'"
            );
            if ($updated > 0) {
                $messages[] = "tl_zotero_item: $updated Einträge mit trash=1 gesetzt (Zotero-Papierkorb).";
            }
        }

        // Attachments: trash aus Parent-Item ableiten (json_data hat ebenfalls deleted)
        if ($this->columnExists('tl_zotero_item_attachment', 'trash')) {
            $updated = $this->connection->executeStatement(
                'UPDATE tl_zotero_item_attachment a
                 INNER JOIN tl_zotero_item i ON i.id = a.pid
                 SET a.trash = 1
                 WHERE i.trash = 1'
            );
            if ($updated > 0) {
                $messages[] = "tl_zotero_item_attachment: $updated Einträge mit trash=1 gesetzt.";
            }
        }

        // 4) Boolean-Spalten: char(1) → tinyint(1)
        $booleanConversions = [
            'tl_zotero_item' => [
                'download_attachments' => 0,
                'published' => 1,
            ],
            'tl_zotero_item_attachment' => [
                'published' => 1,
            ],
            'tl_zotero_library' => [
                'download_attachments' => 0,
                'published' => 1,
                'include_in_sitemap' => 0,
            ],
            'tl_zotero_collection' => [
                'published' => 1,
            ],
        ];

        foreach ($booleanConversions as $table => $columns) {
            if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
                continue;
            }
            foreach ($columns as $col => $default) {
                if (!$this->columnExists($table, $col)) {
                    continue;
                }
                if (!$this->needsBooleanConversion($table, $col)) {
                    continue;
                }
                $defaultVal = (int) $default;
                $this->connection->executeStatement(
                    "ALTER TABLE $table MODIFY $col TINYINT(1) NOT NULL DEFAULT $defaultVal"
                );
                $messages[] = "$table.$col: auf boolean (tinyint) konvertiert.";
            }
        }

        if ($this->connection->createSchemaManager()->tablesExist(['tl_content']) && $this->columnExists('tl_content', 'zotero_download_attachments')) {
            if ($this->needsBooleanConversion('tl_content', 'zotero_download_attachments')) {
                $this->connection->executeStatement(
                    "ALTER TABLE tl_content MODIFY zotero_download_attachments TINYINT(1) NOT NULL DEFAULT 1"
                );
                $messages[] = 'tl_content.zotero_download_attachments: auf boolean (tinyint) konvertiert.';
            }
        }

        return $this->createResult(true, implode(' ', $messages));
    }

    private function columnMissing(string $table, string $column): bool
    {
        return !$this->columnExists($table, $column);
    }

    private function columnExists(string $table, string $column): bool
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);

        return isset($cols[$column]);
    }

    private function isCharColumn(object|null $column): bool
    {
        if ($column === null) {
            return false;
        }

        $typeClass = $column->getType()::class;

        return str_contains($typeClass, 'StringType') || str_contains($typeClass, 'AsciiString');
    }

    private function needsBooleanConversion(string $table, string $column): bool
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);
        if (!isset($cols[$column])) {
            return false;
        }

        return $this->isCharColumn($cols[$column]);
    }
}
