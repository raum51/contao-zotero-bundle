<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Formatiert das Listen-Label für tl_zotero_library.
 * Zeigt Titel plus Anzahl Items und Collections (z. B. „Meine Bibliothek (42 Publikationen, 8 Sammlungen)“).
 */
#[AsCallback(table: 'tl_zotero_library', target: 'list.label.label_callback')]
final class ZoteroLibraryLabelCallback
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(array $row, string $label, DataContainer $dc, array $args = []): string
    {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = 'ID ' . ($row['id'] ?? '');
        }

        $libraryId = (int) ($row['id'] ?? 0);
        if ($libraryId < 1) {
            return $title;
        }

        $itemCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_zotero_item WHERE pid = ?',
            [$libraryId]
        );
        $collectionCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_zotero_collection WHERE pid = ?',
            [$libraryId]
        );

        $lang = $GLOBALS['TL_LANG']['tl_zotero_library'] ?? [];
        $itemsLabel = $lang['items'][0] ?? 'Publications';
        $collectionsLabel = $lang['collections'][0] ?? 'Collections';

        return sprintf(
            '%s (%d %s, %d %s)',
            $title,
            $itemCount,
            $itemsLabel,
            $collectionCount,
            $collectionsLabel
        );
    }
}
