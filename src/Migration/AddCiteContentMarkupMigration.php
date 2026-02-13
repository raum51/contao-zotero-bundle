<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Fügt cite_content_markup zu tl_zotero_library hinzu.
 *
 * Optionen: unchanged, remove_divs, remove_all
 */
final class AddCiteContentMarkupMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: cite_content Markup-Option in tl_zotero_library';
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tl_zotero_library'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_zotero_library');

        return !\in_array('cite_content_markup', array_map(static fn ($c) => $c->getName(), $columns), true);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            "ALTER TABLE tl_zotero_library ADD cite_content_markup varchar(32) NOT NULL default 'unchanged'"
        );

        return $this->createResult(true, 'cite_content_markup in tl_zotero_library ergänzt.');
    }
}
