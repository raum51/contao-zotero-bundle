<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Liefert Zotero-Collections der bearbeiteten Library für sitemap_collections.
 *
 * Eigenständiger Callback für tl_zotero_library, da hier die Library über $dc->id
 * ermittelt wird (nicht über zotero_libraries wie bei tl_content/tl_module).
 *
 * Liegt unter EventListener/DataContainer/ – DCA-Callback für tl_zotero_library.
 */
#[AsCallback(table: 'tl_zotero_library', target: 'fields.sitemap_collections.options')]
final class ZoteroLibrarySitemapCollectionsOptionsCallback
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
        if ($dc === null || !$dc->id) {
            return [];
        }

        $libraryId = (int) $dc->id;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.title, l.title AS library_title FROM tl_zotero_collection c
            INNER JOIN tl_zotero_library l ON l.id = c.pid
            WHERE c.pid = ? AND c.published = ? ORDER BY c.title',
            [$libraryId, '1']
        );

        $options = [];
        foreach ($rows as $row) {
            $collTitle = (string) ($row['title'] ?? 'ID ' . $row['id']);
            $libTitle = (string) ($row['library_title'] ?? '');
            $options[(int) $row['id']] = $libTitle !== '' ? $collTitle . ' (' . $libTitle . ')' : $collTitle;
        }

        return $options;
    }
}
