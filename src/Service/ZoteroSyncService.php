<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Raum51\ContaoZoteroBundle\Service\ZoteroBibUtil;
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
    /** Max Items pro API-Seite (Zotero-API-Limit 100). Weniger Requests durch Batch-Abruf. */
    private const ITEMS_PAGE_SIZE = 100;

    public function __construct(
        private readonly ZoteroClient $zoteroClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly Slug $slug,
    ) {
    }

    /**
     * Sync ausführen (alle Libraries oder eine).
     *
     * @param int|null $libraryId Wenn null: alle Libraries (nur published, wenn $onlyPublished=true)
     * @param bool $onlyPublished Bei sync(null): nur published Libraries synchronisieren
     * @return array{collections_created: int, collections_updated: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, skipped_items: array, attachments_created: int, attachments_updated: int, attachments_deleted: int, attachments_skipped: int, collection_items_created: int, collection_items_deleted: int, item_creators_created: int, item_creators_deleted: int, errors: list<string>}
     */
    public function sync(?int $libraryId = null, bool $onlyPublished = false): array
    {
        $libraries = $this->fetchLibraries($libraryId, $onlyPublished);
        $total = [
            'collections_created' => 0,
            'collections_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'items_skipped' => 0,
            'skipped_items' => [],
            'attachments_created' => 0,
            'attachments_updated' => 0,
            'attachments_deleted' => 0,
            'attachments_skipped' => 0,
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
                $total['attachments_created'] += $result['attachments_created'] ?? 0;
                $total['attachments_updated'] += $result['attachments_updated'] ?? 0;
                $total['attachments_deleted'] += $result['attachments_deleted'] ?? 0;
                $total['attachments_skipped'] += $result['attachments_skipped'] ?? 0;
                foreach ($result['skipped_items'] ?? [] as $skip) {
                    $total['skipped_items'][] = array_merge($skip, ['library' => $library['title'] ?? '']);
                }
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
    private function fetchLibraries(?int $libraryId, bool $onlyPublished = false): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from('tl_zotero_library');
        if ($libraryId !== null) {
            $qb->where($qb->expr()->eq('id', ':id'))->setParameter('id', $libraryId);
        } elseif ($onlyPublished) {
            $qb->where($qb->expr()->eq('published', ':published'))->setParameter('published', '1');
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
     * @return array{collections_created: int, collections_updated: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, skipped_items: array, attachments_created: int, attachments_updated: int, attachments_deleted: int, attachments_skipped: int, collection_items_created: int, collection_items_deleted: int, item_creators_created: int, item_creators_deleted: int}
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
            'skipped_items' => [],
            'attachments_created' => 0,
            'attachments_updated' => 0,
            'attachments_deleted' => 0,
            'attachments_skipped' => 0,
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
     * Items pro Seite gebündelt laden (2 Requests pro Seite statt 2 pro Item).
     *
     * @return array<string, int> zotero_key -> unsere item id
     */
    private function syncItems(string $prefix, string $apiKey, int $pid, string $citationStyle, string $citationLocale, int $since, array &$result): array
    {
        $keyToId = [];
        $path = $prefix . '/items';

        // Pass 1: Alle Nicht-Attachments (itemType=-attachment), damit Parents vor Attachments in keyToId stehen
        $start = 0;
        $limit = self::ITEMS_PAGE_SIZE;
        do {
            $query = ['start' => $start, 'limit' => $limit, 'include' => 'data', 'itemType' => '-attachment'];
            if ($since > 0) {
                $query['since'] = $since;
            }

            $response = $this->zoteroClient->get($path, $apiKey, $query);
            $this->ensureSuccessResponse($response, $path);
            $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
            $items = $this->decodeJson($response->getContent(false), $path);
            if (!\is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $key = $item['key'] ?? '';
                if ($key === '') {
                    continue;
                }
                try {
                    $bibContent = $this->fetchBibtexForItem($path, $apiKey, $key);
                    $citeContent = $this->fetchCiteForItem($path, $apiKey, $key, $citationStyle, $citationLocale);
                    $itemId = $this->upsertItemFromData($pid, $item, $bibContent, $citeContent, $result);
                    if ($itemId > 0) {
                        $keyToId[$key] = $itemId;
                    }
                } catch (\Throwable $e) {
                    $this->recordSkipped($result, $key, $e->getMessage(), null, (string) (($item['data'] ?? [])['itemType'] ?? ''));
                }
            }
            $start += $limit;
        } while (\count($items) === $limit);

        // Pass 2: Alle Attachments (itemType=attachment)
        $start = 0;
        do {
            $query = ['start' => $start, 'limit' => $limit, 'include' => 'data', 'itemType' => 'attachment'];
            if ($since > 0) {
                $query['since'] = $since;
            }

            $response = $this->zoteroClient->get($path, $apiKey, $query);
            $this->ensureSuccessResponse($response, $path);
            $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
            $items = $this->decodeJson($response->getContent(false), $path);
            if (!\is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $key = $item['key'] ?? '';
                if ($key === '') {
                    continue;
                }
                try {
                    $this->upsertAttachmentFromData($pid, $item, $keyToId, $result);
                } catch (\Throwable $e) {
                    $this->recordSkipped($result, $key, $e->getMessage(), (string) (($item['data'] ?? [])['parentItem'] ?? ''), 'attachment');
                }
            }
            $start += $limit;
        } while (\count($items) === $limit);

        return $keyToId;
    }

    /**
     * Übersprungenes Item protokollieren (Zähler + Details für Ausgabe).
     *
     * @param array<string, mixed> $result  Sync-Result-Array (by reference)
     * @param string               $key    Zotero-Item-Key
     * @param string               $reason Grund (z. B. Fehlermeldung)
     * @param string|null          $parentKey Parent-Key bei Attachments
     * @param string               $itemType  Zotero itemType
     */
    private function recordSkipped(array &$result, string $key, string $reason, ?string $parentKey, string $itemType): void
    {
        $this->logger->warning('Zotero Item übersprungen', [
            'key' => $key,
            'reason' => $reason,
            'parent_key' => $parentKey,
        ]);
        $result['items_skipped']++;
        if ($itemType === 'attachment') {
            $result['attachments_skipped'] = ($result['attachments_skipped'] ?? 0) + 1;
        }
        $entry = [
            'key' => $key,
            'reason' => $reason,
            'item_type' => $itemType,
        ];
        if ($parentKey !== null && $parentKey !== '') {
            $entry['parent_key'] = $parentKey;
        }
        $result['skipped_items'][] = $entry;
    }

    /**
     * Attachment-Datensatz in tl_zotero_item_attachment upserten.
     *
     * @param int                $pid      Library-ID (tl_zotero_library.id)
     * @param array<string,mixed> $item    Zotero-Item mit key, version, data
     * @param array<string,int>  $keyToId Mapping zotero_key -> tl_zotero_item.id (für parentItem-Auflösung)
     */
    private function upsertAttachmentFromData(int $pid, array $item, array $keyToId, array &$result): void
    {
        $key = (string) ($item['key'] ?? '');
        $data = $item['data'] ?? [];
        $version = (int) ($item['version'] ?? 0);
        $parentKey = (string) ($data['parentItem'] ?? '');

        if ($parentKey === '') {
            $this->recordSkipped($result, $key, 'Attachment ohne parentItem (Standalone oder defekt)', null, 'attachment');
            return;
        }

        // Parent-Item-ID: bevorzugt aus aktuellem Sync-Mapping, sonst aus DB
        $parentId = $keyToId[$parentKey] ?? null;
        if ($parentId === null) {
            $found = $this->connection->fetchOne(
                'SELECT id FROM tl_zotero_item WHERE pid = ? AND zotero_key = ?',
                [$pid, $parentKey]
            );
            if ($found === false) {
                $this->recordSkipped(
                    $result,
                    $key,
                    'Parent-Item nicht in tl_zotero_item (z. B. gelöscht in Zotero, oder Pagination)',
                    $parentKey,
                    'attachment'
                );
                return;
            }
            $parentId = (int) $found;
        }

        $jsonData = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $existing = $this->connection->fetchOne(
            'SELECT id FROM tl_zotero_item_attachment WHERE pid = ? AND zotero_key = ?',
            [$parentId, $key]
        );

        $row = [
            'pid' => $parentId,
            'tstamp' => time(),
            'sorting' => 0,
            'zotero_key' => $key,
            'zotero_version' => $version,
            'link_mode' => (string) ($data['linkMode'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'filename' => (string) ($data['filename'] ?? ''),
            'content_type' => (string) ($data['contentType'] ?? ''),
            'url' => (string) ($data['url'] ?? ''),
            'charset' => (string) ($data['charset'] ?? ''),
            'md5' => (string) ($data['md5'] ?? ''),
            'json_data' => $jsonData,
            'published' => '1',
        ];

        if ($existing !== false) {
            unset($row['pid'], $row['sorting']);
            $this->connection->update('tl_zotero_item_attachment', $row, ['id' => $existing]);
            $result['attachments_updated']++;
        } else {
            $this->connection->insert('tl_zotero_item_attachment', $row);
            $result['attachments_created']++;
        }
    }

    /**
     * Holt BibTeX für ein einzelnes Item (GET .../items/{key}?format=bibtex).
     * Bei Fehler oder leeren Typen (z. B. Attachment) wird '' zurückgegeben.
     */
    private function fetchBibtexForItem(string $itemsPath, string $apiKey, string $key): string
    {
        $path = $itemsPath . '/' . $key;
        try {
            $response = $this->zoteroClient->get($path, $apiKey, ['format' => 'bibtex']);
            $this->ensureSuccessResponse($response, $path);
            $content = trim($response->getContent(false));
            return $content;
        } catch (\Throwable $e) {
            $this->logger->debug('BibTeX für Item nicht abrufbar', ['key' => $key, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Formatierten Literaturverweis (HTML) für ein Item nachladen (GET …/items/{key}?include=bib&style=…).
     */
    private function fetchCiteForItem(string $itemsPath, string $apiKey, string $key, string $citationStyle, string $citationLocale): string
    {
        $path = $itemsPath . '/' . $key;
        $query = ['include' => 'bib'];
        if ($this->isValidCitationStyle($citationStyle)) {
            $query['style'] = $citationStyle;
        }
        if ($citationLocale !== '') {
            $query['locale'] = $citationLocale;
        }
        try {
            $response = $this->zoteroClient->get($path, $apiKey, $query);
            $this->ensureSuccessResponse($response, $path);
            $decoded = $this->decodeJson($response->getContent(false), $path);
            $bib = $decoded['bib'] ?? '';
            return \is_string($bib) ? trim($bib) : '';
        } catch (\Throwable $e) {
            $this->logger->debug('Zitation für Item nicht abrufbar', ['key' => $key, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Einzelnes Item in die DB schreiben (ohne weiteren API-Request). Daten aus Batch-Response, bib/cite bereits nachgeladen.
     *
     * @param array $item Item von API mit key, version, data
     */
    private function upsertItemFromData(int $pid, array $item, string $bibContent, string $citeContent, array &$result): int
    {
        $key = $item['key'] ?? '';
        $data = $item['data'] ?? [];
        $version = (int) ($item['version'] ?? 0);

        $title = $data['title'] ?? '';
        $itemType = $data['itemType'] ?? '';
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

        $citeKey = ZoteroBibUtil::extractCiteKeyFromBib($bibContent);
        $excludeId = $existing !== false ? (int) $existing : null;
        $aliasExists = function (string $a) use ($excludeId): bool {
            if ($excludeId !== null) {
                $e = $this->connection->fetchOne('SELECT id FROM tl_zotero_item WHERE alias = ? AND id != ?', [$a, $excludeId]);
            } else {
                $e = $this->connection->fetchOne('SELECT id FROM tl_zotero_item WHERE alias = ?', [$a]);
            }
            return $e !== false;
        };
        $aliasSource = $citeKey !== '' ? $citeKey : ($title !== '' ? $title : 'item-' . $key);
        $alias = $this->slug->generate($aliasSource, [], $aliasExists);

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
            'alias' => $alias,
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

    /**
     * Setzt die Sync-Metadaten aller Libraries zurück (Vollabzug beim nächsten Sync).
     */
    public function resetAllSyncStates(): void
    {
        $tstamp = time();
        $this->connection->executeStatement(
            'UPDATE tl_zotero_library SET tstamp = ?, last_sync_version = 0, last_sync_at = 0, last_sync_status = ?',
            [$tstamp, '']
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
        $hint = $status >= 500
            ? 'Serverfehler bei Zotero (z. B. Timeout oder fehlerhafte Items/Styles). Später erneut versuchen oder kleinere Bibliothek.'
            : 'Prüfe API-Key und Library-ID.';
        throw new \RuntimeException(
            sprintf('Zotero API %s: HTTP %d. %s', $path, $status, $hint)
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
