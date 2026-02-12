<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;

/**
 * Liefert Zotero-Collections der gewählten Bibliothek als Options für Checkbox-Wizard.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback
 * (fields.zotero_collections.options) für tl_module ist. Collections werden
 * nach der ausgewählten Library (zotero_library) gefiltert – bei Änderung der
 * Library muss die Seite neu geladen werden (submitOnChange auf zotero_library).
 */
#[AsCallback(table: 'tl_module', target: 'fields.zotero_collections.options')]
final class ZoteroCollectionsOptionsCallback
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
        $libraryId = $this->getLibraryId($dc);
        if ($libraryId <= 0) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM tl_zotero_collection WHERE pid = ? AND published = ? ORDER BY title',
            [$libraryId, '1']
        );

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) ($row['title'] ?? 'ID ' . $row['id']);
        }

        return $options;
    }

    private function getLibraryId(DataContainer|null $dc): int
    {
        if ($dc === null) {
            return 0;
        }

        // Nach Submit (z.B. submitOnChange) enthält getCurrentRecord die neuen Werte
        $record = $dc->getCurrentRecord();
        if (isset($record['zotero_library']) && (int) $record['zotero_library'] > 0) {
            return (int) $record['zotero_library'];
        }

        // Fallback: POST-Daten bei noch nicht gespeichertem Datensatz
        $post = Input::post('zotero_library');
        if ($post !== null && (int) $post > 0) {
            return (int) $post;
        }

        return 0;
    }
}
