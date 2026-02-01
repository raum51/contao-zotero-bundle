
<?php

namespace raum51\ContaoZoteroBundle\Service;

use Contao\System;
use raum51\ContaoZoteroBundle\Model\ZoteroLibraryModel;

class ZoteroSynchronizer
{
    public function __construct(
        private ZoteroApiClient $apiClient,
        private ZoteroImporter $importer
    ) {}

    /**
     * Synchronize all enabled libraries.
     */
    public function syncAll(): void
    {
        $libraries = \Database::getInstance()->prepare('SELECT * FROM tl_zotero_library WHERE enabled=?')->execute('1');
        while ($libraries->next()) {
            $this->syncLibrary($libraries->row());
        }
    }

    /**
     * @param array $libraryRow Row from tl_zotero_library
     */
    public function syncLibrary(array $libraryRow): void
    {
        $type = $libraryRow['type'];
        $libId = $libraryRow['libraryId'];
        $apiKey = $libraryRow['apiKey'];

        // Collections
        $collections = $this->apiClient->fetchCollections($type, $libId, $apiKey, [
            'limit' => 100,
            'start' => 0,
            'sort' => 'title'
        ]);
        $this->importer->importCollections($libId, $collections);

        // Items (basic, no pagination here in skeleton)
        $items = $this->apiClient->fetchItems($type, $libId, $apiKey, [
            'limit' => 100,
            'start' => 0,
            'include' => 'data'
        ]);
        $this->importer->importItems($libId, $items);

        // Update lastSync timestamp
        \Database::getInstance()->prepare('UPDATE tl_zotero_library SET lastSync=?, tstamp=? WHERE id=?')
            ->execute(time(), time(), (int)$libraryRow['id']);
    }
}
