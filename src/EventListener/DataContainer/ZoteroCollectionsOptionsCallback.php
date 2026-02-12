<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;

/**
 * Liefert Zotero-Collections der gewählten Bibliotheken als Options für Checkbox-Wizard.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback
 * (fields.zotero_collections.options) für tl_module ist. Collections werden
 * nach den ausgewählten Libraries (zotero_libraries) gefiltert – bei Änderung
 * muss die Seite neu geladen werden (submitOnChange auf zotero_libraries).
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
        $libraryIds = $this->getLibraryIds($dc);
        if ($libraryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($libraryIds), '?'));
        $params = array_merge($libraryIds, ['1']);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.title, l.title AS library_title FROM tl_zotero_collection c
            INNER JOIN tl_zotero_library l ON l.id = c.pid
            WHERE c.pid IN (' . $placeholders . ') AND c.published = ? ORDER BY l.title, c.title',
            $params
        );

        $options = [];
        foreach ($rows as $row) {
            $collTitle = (string) ($row['title'] ?? 'ID ' . $row['id']);
            $libTitle = (string) ($row['library_title'] ?? '');
            $options[(int) $row['id']] = $libTitle !== '' ? $collTitle . ' (' . $libTitle . ')' : $collTitle;
        }

        return $options;
    }

    /**
     * @return list<int>
     */
    private function getLibraryIds(DataContainer|null $dc): array
    {
        if ($dc === null) {
            return [];
        }

        // Nach Submit (z.B. submitOnChange) enthält getCurrentRecord die neuen Werte
        $record = $dc->getCurrentRecord();
        if (isset($record['zotero_libraries'])) {
            return $this->parseLibraryIds($record['zotero_libraries']);
        }

        // Fallback: POST-Daten bei noch nicht gespeichertem Datensatz
        $post = Input::post('zotero_libraries');
        if (\is_array($post)) {
            return array_map('intval', array_filter($post, 'is_numeric'));
        }

        return [];
    }

    /**
     * @param mixed $value Serialisierte oder Array-Werte
     *
     * @return list<int>
     */
    private function parseLibraryIds(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_map('intval', array_filter($value, 'is_numeric')));
        }
        if (\is_string($value)) {
            $ids = unserialize($value, ['allowed_classes' => false]);
            if (\is_array($ids)) {
                return array_values(array_map('intval', array_filter($ids, 'is_numeric')));
            }
        }

        return [];
    }
}
