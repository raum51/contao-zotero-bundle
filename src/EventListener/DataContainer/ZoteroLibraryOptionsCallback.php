<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert publizierte Zotero-Bibliotheken als Options für Select-Felder.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback
 * (fields.zotero_libraries.options) für tl_module.
 * Contao's relation/foreignKey mit where-Klausel funktioniert für
 * benutzerdefinierte Tabellen offenbar nicht zuverlässig – dieser
 * Callback lädt die Optionen explizit aus der Datenbank.
 */
#[AsCallback(table: 'tl_module', target: 'fields.zotero_libraries.options')]
#[AsCallback(table: 'tl_content', target: 'fields.zotero_libraries.options')]
final class ZoteroLibraryOptionsCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<int, string> id => title
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM tl_zotero_library WHERE published = ? ORDER BY title',
            ['1']
        );

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) ($row['title'] ?? 'ID ' . $row['id']);
        }

        return $options;
    }
}
