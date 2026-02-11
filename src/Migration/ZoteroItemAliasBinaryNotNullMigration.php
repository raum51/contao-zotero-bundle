<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroBibUtil;

/**
 * Stellt tl_zotero_item.alias auf Contao-Standard um: BINARY NOT NULL DEFAULT ''.
 * Nur für Installationen, die die Spalte bereits als NULL hatten.
 *
 * Liegt in src/Migration/, da Contao Migrationen hier erwartet.
 */
final class ZoteroItemAliasBinaryNotNullMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Slug $slug,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero-Bundle: tl_zotero_item.alias auf BINARY NOT NULL (Contao-Standard) umstellen';
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_zotero_item'])) {
            return false;
        }
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_zotero_item');
        if (!isset($columns['alias'])) {
            return false;
        }
        $alias = $columns['alias'];
        return !$alias->getNotnull();
    }

    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, bib_content, alias FROM tl_zotero_item WHERE alias IS NULL OR alias = ?',
            ['']
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

        $this->connection->executeStatement("
            ALTER TABLE tl_zotero_item
            MODIFY COLUMN alias varchar(255) BINARY NOT NULL DEFAULT ''
        ");

        return $this->createResult(true, 'Spalte alias auf BINARY NOT NULL umgestellt.');
    }
}
