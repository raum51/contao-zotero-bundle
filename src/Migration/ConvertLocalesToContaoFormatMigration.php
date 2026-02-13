<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Konvertiert Locale-Werte in tl_zotero_locales von Zotero-Format (de-AT) zu Contao-Format (de_AT).
 */
final class ConvertLocalesToContaoFormatMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Zotero: Locales in tl_zotero_locales zu Contao-Format (Unterstrich) konvertieren';
    }

    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_zotero_locales'])) {
            return false;
        }

        $hasHyphen = $this->connection->fetchOne(
            "SELECT 1 FROM tl_zotero_locales WHERE locale LIKE '%-%' LIMIT 1"
        );

        return $hasHyphen !== false;
    }

    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, locale FROM tl_zotero_locales WHERE locale LIKE '%-%'"
        );

        foreach ($rows as $row) {
            $newLocale = str_replace('-', '_', (string) $row['locale']);
            $this->connection->update(
                'tl_zotero_locales',
                ['locale' => $newLocale],
                ['id' => (int) $row['id']]
            );
        }

        return $this->createResult(true, sprintf('%d Locale(s) zu Contao-Format konvertiert.', \count($rows)));
    }
}
