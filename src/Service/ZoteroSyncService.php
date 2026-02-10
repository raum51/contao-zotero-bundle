<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Synchronisiert Zotero-Bibliotheken (Collections, Items, cite/bib, Tags)
 * in die lokalen Tabellen tl_zotero_*.
 *
 * Liegt in src/Service/, da es die zentrale Sync-Logik bündelt. Nutzt ZoteroClient
 * und speichert über Doctrine Connection (Contao 5.6+).
 */
final class ZoteroSyncService
{
    private const ITEMS_PAGE_SIZE = 50;

    public function __construct(
        private readonly ZoteroClient $zoteroClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sync ausführen (alle Libraries oder eine).
     *
     * @return array{collections_created: int, collections_updated: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, collection_items_created: int, collection_items_deleted: int, item_creators_created: int, item_creators_deleted: int, errors: list<string>}
     */
    public function sync(?int $libraryId = null): array
    {
        $libraries = $this->fetchLibraries($libraryId);
        $total = [
            'collections_created' => 0,
            'collections_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'items_skipped' => 0,
            'collection_items_created' => 0,
            'collection_items_deleted' => 0,
            'item_creators_created' => 0,
            'item_creators_deleted' => 0,
            'errors' => [],
        ];

        foreach ($libraries as $library) {
            try {
                $result = $this->syncLibrary($library);
                $total['collections_created'] += $result['collections_created'];
                $total['collections_updated'] += $result['collections_updated'];
                $total['items_created'] += $result['items_created'];
                $total['items_updated'] += $result['items_updated'];
                $total['items_deleted'] += $result['items_deleted'];
                $total['items_skipped'] += $result['items_skipped'];
                $total['collection_items_created'] += $result['collection_items_created'];
                $total['collection_items_deleted'] += $result['collection_items_deleted'];
                $total['item_creators_created'] += $result['item_creators_created'];
                $total['item_creators_deleted'] += $result['item_creators_deleted'];
            } catch (\Throwable $e) {
                $this->logger->error('Zotero Sync fehlgeschlagen', [
                    'library_id' => $library['id'],
                    'title' => $library['title'],
                    'error' => $e->getMessage(),
                ]);
                $total['errors'][] = $library['title'] . ': ' . $e->getMessage();
            }
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLibraries(?int $libraryId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from('tl_zotero_library');
        if ($libraryId !== null) {
            $qb->where($qb->expr()->eq('id', ':id'))->setParameter('id', $libraryId);
        }
        $stmt = $qb->executeQuery();
        $rows = $stmt->fetchAllAssociative();

        return \is_array($rows) ? $rows : [];
    }

    private function libraryPrefix(array $library): string
    {
        $type = $library['library_type'] ?? 'user';
        $id = $library['library_id'] ?? '';

        return $type === 'group' ? 'groups/' . $id : 'users/' . $id;
    }

    /**
     * @return array{collections_created: int, collections_updated: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, collection_items_created: int, collection_items_deleted: int, item_creators_created: int, item_creators_deleted: int}
     */
    private function syncLibrary(array $library): array
    {
        $pid = (int) $library['id'];
        $apiKey = (string) $library['api_key'];
        $prefix = $this->libraryPrefix($library);
        $citationStyle = (string) ($library['citation_style'] ?? '');
        $citationLocale = (string) ($library['citation_locale'] ?? 'en-US');
        $lastVersion = (int) ($library['last_sync_version'] ?? 0);

        $this->logger->info('Zotero Sync start', ['library' => $library['title'], 'prefix' => $prefix]);

        $result = [
            'collections_created' => 0,
            'collections_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'items_skipped' => 0,
            'collection_items_created' => 0,
            'collection_items_deleted' => 0,
            'item_creators_created' => 0,
            'item_creators_deleted' => 0,
        ];

        // 1) Collections
        $collectionKeyToId = $this->syncCollections($prefix, $apiKey, $pid, $result);

        // 2) Items (mit since für inkrementell; bei 0 lokalen Items Vollabzug, damit nach manueller Löschung wieder alle geholt werden)
        $localItemCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_zotero_item WHERE pid = ?', [$pid]);
        $effectiveSince = $localItemCount > 0 ? $lastVersion : 0;
        $itemKeyToId = $this->syncItems($prefix, $apiKey, $pid, $citationStyle, $citationLocale, $effectiveSince, $result);

        // 3) Collection-Item-Verknüpfungen
        $this->syncCollectionItems($prefix, $apiKey, $pid, $collectionKeyToId, $itemKeyToId, $result);

        // 4) Item-Creator-Verknüpfungen (creator_map wird bei Bedarf angelegt, member_id=0)
        $this->syncItemCreators($pid, $result);

        // 5) Library-Metadaten (last_sync_*, Version aus letztem Items-Request)
        $newVersion = $this->getLastModifiedVersionFromCache();
        $this->updateLibrarySyncStatus($pid, $newVersion);

        $this->logger->info('Zotero Sync fertig', ['library' => $library['title'], 'result' => $result]);

        return $result;
    }

    /** @var int|null */
    private $lastModifiedVersion = null;

    private function getLastModifiedVersionFromCache(): ?int
    {
        $v = $this->lastModifiedVersion;
        $this->lastModifiedVersion = null;

        return $v;
    }

    /**
     * @return array<string, int> zotero_key -> unsere collection id
     */
    private function syncCollections(string $prefix, string $apiKey, int $pid, array &$result): array
    {
        $path = $prefix . '/collections';
        $keyToId = [];
        $keyToParentKey = [];

        $start = 0;
        $limit = 100;
        do {
            $response = $this->zoteroClient->get($path, $apiKey, ['start' => $start, 'limit' => $limit]);
            $this->ensureSuccessResponse($response, $path);
            $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
            $json = $response->getContent(false);
            $collections = $this->decodeJson($json, $path);
            if (!\is_array($collections)) {
                break;
            }
            foreach ($collections as $c) {
                $key = $c['key'] ?? '';
                $data = $c['data'] ?? [];
                $name = $data['name'] ?? '';
                $parentKey = $data['parentCollection'] ?? false;
                if ($parentKey === false) {
                    $parentKey = '';
                }
                $keyToParentKey[$key] = (string) $parentKey;

                $existing = $this->connection->fetchAssociative(
                    'SELECT id, title FROM tl_zotero_collection WHERE pid = ? AND zotero_key = ?',
                    [$pid, $key]
                );

                if ($existing !== false && \is_array($existing)) {
                    $updates = [];

                    if ((string) $existing['title'] !== $name) {
                        $updates['title'] = $name;
                    }

                    if ($updates !== []) {
                        $updates['tstamp'] = time();
                        $this->connection->update('tl_zotero_collection', $updates, ['id' => $existing['id']]);
                        $result['collections_updated']++;
                    }

                    $keyToId[$key] = (int) $existing['id'];
                } else {
                    $this->connection->insert('tl_zotero_collection', [
                        'pid' => $pid,
                        'tstamp' => time(),
                        'parent_id' => null,
                        'sorting' => 0,
                        'zotero_key' => $key,
                        'title' => $name,
                        'published' => '1',
                    ]);
                    $keyToId[$key] = (int) $this->connection->lastInsertId();
                    $result['collections_created']++;
                }
            }
            $start += $limit;
        } while (\count($collections) === $limit);

        // Parent-IDs setzen (zweiter Durchlauf)
        foreach ($keyToParentKey as $key => $parentKey) {
            if ($parentKey === '' || !isset($keyToId[$key], $keyToId[$parentKey])) {
                continue;
            }
            $this->connection->update('tl_zotero_collection', [
                'parent_id' => $keyToId[$parentKey],
            ], ['id' => $keyToId[$key]]);
        }

        return $keyToId;
    }

    /**
     * @return array<string, int> zotero_key -> unsere item id
     */
    private function syncItems(string $prefix, string $apiKey, int $pid, string $citationStyle, string $citationLocale, int $since, array &$result): array
    {
        $keyToId = [];
        $start = 0;
        $limit = self::ITEMS_PAGE_SIZE;
        $query = ['limit' => $limit];
        if ($since > 0) {
            $query['since'] = $since;
        }

        do {
            $query['start'] = $start;
            $response = $this->zoteroClient->get($prefix . '/items/top', $apiKey, $query);
            $this->ensureSuccessResponse($response, $prefix . '/items/top');
            $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
            $items = $this->decodeJson($response->getContent(false), $prefix . '/items/top');
            if (!\is_array($items)) {
                break;
            }
            foreach ($items as $item) {
                $key = $item['key'] ?? '';
                if ($key === '') {
                    continue;
                }
                try {
                    $itemId = $this->upsertItem($prefix, $apiKey, $pid, $key, $item, $citationStyle, $citationLocale, $result);
                    if ($itemId > 0) {
                        $keyToId[$key] = $itemId;
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Zotero Item übersprungen (API-Fehler)', [
                        'key' => $key,
                        'path' => $prefix . '/items/' . $key,
                        'error' => $e->getMessage(),
                    ]);
                    $result['items_skipped']++;
                }
            }
            $start += $limit;
        } while (\count($items) === $limit);

        return $keyToId;
    }

    /**
     * Einzelnes Item: Metadaten + cite (include=bib) + bib (format=bibtex).
     */
    private function upsertItem(string $prefix, string $apiKey, int $pid, string $key, array $listItem, string $citationStyle, string $citationLocale, array &$result): int
    {
        $path = $prefix . '/items/' . $key;
        $includeQuery = ['include' => 'data,bib'];
        // Nur gültige Zotero-Styles senden (Name aus Style-Repo oder URL zu .csl). Platzhalter wie "CSL-URL" führen zu HTTP 500.
        if ($this->isValidCitationStyle($citationStyle)) {
            $includeQuery['style'] = $citationStyle;
        }
        if ($citationLocale !== '') {
            $includeQuery['locale'] = $citationLocale;
        }
        $fullItemResponse = $this->zoteroClient->get($path, $apiKey, $includeQuery);
        $this->ensureSuccessResponse($fullItemResponse, $path);
        $fullItem = $this->decodeJson($fullItemResponse->getContent(false), $path);
        if (!\is_array($fullItem)) {
            return 0;
        }
        $data = $fullItem['data'] ?? $listItem['data'] ?? [];
        $citeContent = $fullItem['bib'] ?? '';

        $bibResponse = $this->zoteroClient->get($path, $apiKey, ['format' => 'bibtex']);
        $this->ensureSuccessResponse($bibResponse, $path . '?format=bibtex');
        $bibContent = $bibResponse->getContent(false);

        $title = $data['title'] ?? '';
        $itemType = $data['itemType'] ?? '';
        $version = (int) ($fullItem['version'] ?? $listItem['version'] ?? 0);
        $year = '';
        $date = $data['date'] ?? '';
        if (preg_match('/\b(19|20)\d{2}\b/', $date, $m)) {
            $year = $m[0];
        }
        $publicationTitle = $data['publicationTitle'] ?? '';
        $tags = $data['tags'] ?? [];
        $tagsJson = $tags !== [] ? json_encode($tags, \JSON_UNESCAPED_UNICODE) : null;
        $jsonData = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $existing = $this->connection->fetchOne('SELECT id FROM tl_zotero_item WHERE pid = ? AND zotero_key = ?', [$pid, $key]);
        $tstamp = time();
        $row = [
            'pid' => $pid,
            'tstamp' => $tstamp,
            'zotero_key' => $key,
            'zotero_version' => $version,
            'title' => $title,
            'item_type' => $itemType,
            'year' => $year,
            'date' => $date,
            'publication_title' => $publicationTitle,
            'cite_content' => $citeContent,
            'bib_content' => $bibContent,
            'json_data' => $jsonData,
            'tags' => $tagsJson,
            'download_attachments' => '',
            'published' => '1',
        ];

        if ($existing !== false) {
            unset($row['pid']);
            $this->connection->update('tl_zotero_item', $row, ['id' => $existing]);
            $result['items_updated']++;
            return (int) $existing;
        }
        $this->connection->insert('tl_zotero_item', $row);
        $result['items_created']++;
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Collection-Item-Verknüpfungen synchronisieren. Entfernt Verknüpfungen, die nicht mehr in der Collection sind.
     *
     * @param array<string, int> $collectionKeyToId
     * @param array<string, int> $itemKeyToId
     * @param array{collection_items_created: int, collection_items_deleted: int} $result
     */
    private function syncCollectionItems(string $prefix, string $apiKey, int $pid, array $collectionKeyToId, array $itemKeyToId, array &$result): void
    {
        foreach ($collectionKeyToId as $collKey => $collectionId) {
            $path = $prefix . '/collections/' . $collKey . '/items';
            $response = $this->zoteroClient->get($path, $apiKey, ['limit' => 100]);
            $this->ensureSuccessResponse($response, $path);
            $items = $this->decodeJson($response->getContent(false), $path);
            if (!\is_array($items)) {
                // Keine Items in Collection: alle Verknüpfungen löschen
                $deleted = $this->connection->delete('tl_zotero_collection_item', ['collection_id' => $collectionId]);
                $result['collection_items_deleted'] += $deleted;
                continue;
            }

            // 1) Erwartete Item-IDs aus der API sammeln
            $expectedItemIds = [];
            foreach ($items as $item) {
                $itemKey = $item['key'] ?? trim((string) $item);
                if ($itemKey === '' || !isset($itemKeyToId[$itemKey])) {
                    continue;
                }
                $expectedItemIds[] = $itemKeyToId[$itemKey];
            }

            // 2) Bestehende Verknüpfungen für diese Collection holen
            $existingLinks = $this->connection->fetchAllAssociative(
                'SELECT item_id FROM tl_zotero_collection_item WHERE collection_id = ?',
                [$collectionId]
            );
            $existingItemIds = array_map(static fn (array $row) => (int) $row['item_id'], $existingLinks);

            // 3) Verknüpfungen löschen, die nicht mehr erwartet werden
            $toDelete = array_diff($existingItemIds, $expectedItemIds);
            foreach ($toDelete as $itemId) {
                $deleted = $this->connection->delete('tl_zotero_collection_item', [
                    'collection_id' => $collectionId,
                    'item_id' => $itemId,
                ]);
                $result['collection_items_deleted'] += $deleted;
            }

            // 4) Neue Verknüpfungen anlegen (die noch nicht existieren)
            $toCreate = array_diff($expectedItemIds, $existingItemIds);
            foreach ($toCreate as $itemId) {
                $this->connection->insert('tl_zotero_collection_item', [
                    'pid' => $collectionId,
                    'tstamp' => time(),
                    'collection_id' => $collectionId,
                    'item_id' => $itemId,
                ]);
                $result['collection_items_created']++;
            }
        }
    }

    /**
     * Verknüpfungen item <-> creator_map. Creator-Map-Einträge werden bei Bedarf angelegt (member_id=0).
     * Entfernt Verknüpfungen, die nicht mehr im Item vorhanden sind.
     *
     * @param array{item_creators_created: int, item_creators_deleted: int} $result
     */
    private function syncItemCreators(int $pid, array &$result): void
    {
        $items = $this->connection->fetchAllAssociative(
            'SELECT id, json_data FROM tl_zotero_item WHERE pid = ? AND json_data IS NOT NULL AND json_data != ?',
            [$pid, '']
        );
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $data = json_decode((string) $item['json_data'], true);
            if (!\is_array($data)) {
                // Keine Creator-Daten: alle Verknüpfungen löschen
                $deleted = $this->connection->delete('tl_zotero_item_creator', ['item_id' => $itemId]);
                $result['item_creators_deleted'] += $deleted;
                continue;
            }
            $creators = $data['creators'] ?? [];
            $expectedCreatorMapIds = [];

            // 1) Alle Creator aus json_data sammeln und Creator-Map-IDs finden/erstellen
            foreach ($creators as $creator) {
                $firstName = trim((string) ($creator['firstName'] ?? ''));
                $lastName = trim((string) ($creator['lastName'] ?? ''));
                if ($lastName === '' && isset($creator['name'])) {
                    $lastName = trim((string) $creator['name']);
                }
                if ($firstName === '' && $lastName === '') {
                    continue;
                }
                $creatorMapId = $this->connection->fetchOne(
                    'SELECT id FROM tl_zotero_creator_map WHERE zotero_firstname = ? AND zotero_lastname = ?',
                    [$firstName, $lastName]
                );
                if ($creatorMapId === false) {
                    $this->connection->insert('tl_zotero_creator_map', [
                        'tstamp' => time(),
                        'zotero_firstname' => $firstName,
                        'zotero_lastname' => $lastName,
                        'member_id' => 0,
                    ]);
                    $creatorMapId = (int) $this->connection->lastInsertId();
                } else {
                    $creatorMapId = (int) $creatorMapId;
                }
                $expectedCreatorMapIds[] = $creatorMapId;
            }

            // 2) Alle bestehenden Verknüpfungen für dieses Item holen
            $existingLinks = $this->connection->fetchAllAssociative(
                'SELECT creator_map_id FROM tl_zotero_item_creator WHERE item_id = ?',
                [$itemId]
            );
            $existingCreatorMapIds = array_map(static fn (array $row) => (int) $row['creator_map_id'], $existingLinks);

            // 3) Verknüpfungen löschen, die nicht mehr erwartet werden
            $toDelete = array_diff($existingCreatorMapIds, $expectedCreatorMapIds);
            foreach ($toDelete as $creatorMapId) {
                $deleted = $this->connection->delete('tl_zotero_item_creator', [
                    'item_id' => $itemId,
                    'creator_map_id' => $creatorMapId,
                ]);
                $result['item_creators_deleted'] += $deleted;
            }

            // 4) Neue Verknüpfungen anlegen (die noch nicht existieren)
            $toCreate = array_diff($expectedCreatorMapIds, $existingCreatorMapIds);
            foreach ($toCreate as $creatorMapId) {
                $this->connection->insert('tl_zotero_item_creator', [
                    'pid' => $itemId,
                    'tstamp' => time(),
                    'item_id' => $itemId,
                    'creator_map_id' => $creatorMapId,
                ]);
                $result['item_creators_created']++;
            }
        }
    }

    /**
     * Setzt die Sync-Metadaten einer Library zurück (last_sync_version=0 etc.).
     * Nächster Sync holt dann wieder alle Items (Vollabzug).
     */
    public function resetSyncState(int $libraryId): void
    {
        $this->connection->update(
            'tl_zotero_library',
            [
                'tstamp' => time(),
                'last_sync_version' => 0,
                'last_sync_at' => 0,
                'last_sync_status' => '',
            ],
            ['id' => $libraryId]
        );
    }

    private function updateLibrarySyncStatus(int $libraryId, ?int $newVersion): void
    {
        $set = [
            'tstamp' => time(),
            'last_sync_at' => time(),
            'last_sync_status' => 'OK',
        ];
        if ($newVersion !== null) {
            $set['last_sync_version'] = $newVersion;
        }
        $this->connection->update('tl_zotero_library', $set, ['id' => $libraryId]);
    }

    private function isValidCitationStyle(string $style): bool
    {
        $style = trim($style);
        if ($style === '') {
            return false;
        }
        $invalid = ['csl-url', 'csl_url', 'url', 'style'];
        if (\in_array(strtolower($style), $invalid, true)) {
            return false;
        }
        return true;
    }

    private function parseLastModifiedVersion(ResponseInterface $response): ?int
    {
        try {
            $headers = $response->getHeaders(false);
            $key = 'last-modified-version';
            foreach ($headers as $name => $values) {
                if (strtolower((string) $name) === $key && isset($values[0])) {
                    return (int) $values[0];
                }
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function ensureSuccessResponse(ResponseInterface $response, string $path): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }
        throw new \RuntimeException(
            sprintf('Zotero API %s: HTTP %d. Prüfe API-Key und Library-ID.', $path, $status)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $json, string $context): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
            return \is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            $preview = substr($json, 0, 200);
            throw new \RuntimeException(
                sprintf('Zotero API (%s): Kein gültiges JSON. %s. Vorschau: %s', $context, $e->getMessage(), $preview),
                0,
                $e
            );
        }
    }
}
