<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroBibUtil;

/**
 * Fügt tl_zotero_item.alias hinzu (cite_key aus Zotero-BibTeX), UNIQUE.
 * Bestehende Einträge: alias aus bib_content per Contao-Slug erzeugen, bei Kollision -1, -2 (Contao-Konvention).
 *
 * Liegt in src/Migration/, da Contao Migrationen hier erwartet.
 */
final class AddZoteroItemAliasMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Slug $slug,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: tl_zotero_item.alias (cite_key) anlegen und befüllen';
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_zotero_item'])) {
            return false;
        }
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_zotero_item');

        return !isset($columns['alias']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("
            ALTER TABLE tl_zotero_item
            ADD COLUMN alias varchar(255) BINARY NOT NULL DEFAULT ''
        ");

        $this->backfillAliases();

        $this->connection->executeStatement("
            ALTER TABLE tl_zotero_item
            ADD UNIQUE KEY alias (alias)
        ");

        return $this->createResult(true, 'Spalte alias angelegt und aus bib_content befüllt.');
    }

    private function backfillAliases(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, bib_content FROM tl_zotero_item'
        );
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $citeKey = ZoteroBibUtil::extractCiteKeyFromBib((string) ($row['bib_content'] ?? ''));
            $source = $citeKey !== '' ? $citeKey : 'item-' . $id;
            $aliasExists = function (string $alias) use ($id): bool {
                $existing = $this->connection->fetchOne(
                    'SELECT id FROM tl_zotero_item WHERE alias = ? AND id != ?',
                    [$alias, $id]
                );
                return $existing !== false;
            };
            $alias = $this->slug->generate($source, [], $aliasExists);
            $this->connection->update(
                'tl_zotero_item',
                ['alias' => $alias],
                ['id' => $id]
            );
        }
    }
}
