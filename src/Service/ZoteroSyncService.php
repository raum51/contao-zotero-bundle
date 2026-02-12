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
        private readonly ApiLogCollector $apiLogCollector,
    ) {
    }

    /**
     * Sync ausführen (alle Libraries oder eine).
     *
     * @param int|null $libraryId Wenn null: alle Libraries (nur published, wenn $onlyPublished=true)
     * @param bool $onlyPublished Bei sync(null): nur published Libraries synchronisieren
     * @param string|null $apiLogPath Optional: API-Aufrufe als JSON in diese Datei protokollieren
     * @param array<string, mixed> $apiLogMetadata Metadaten für das API-Log (command, timestamp, options)
     * @return array{collections_created: int, collections_updated: int, collections_deleted: int, collections_skipped: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, skipped_items: array, items_updated_details: array, items_deleted_details: array, attachments_created: int, attachments_updated: int, attachments_deleted: int, attachments_skipped: int, attachments_updated_details: array, attachments_deleted_details: array, collection_items_created: int, collection_items_deleted: int, collection_items_skipped: int, collection_items_deleted_details: array, item_creators_created: int, item_creators_deleted: int, item_creators_skipped: int, errors: list<string>}
     */
    public function sync(?int $libraryId = null, bool $onlyPublished = false, ?string $apiLogPath = null, array $apiLogMetadata = []): array
    {
        if ($apiLogPath !== null && $apiLogPath !== '') {
            $this->apiLogCollector->enable($apiLogPath, array_merge(
                ['timestamp' => date(\DateTimeInterface::ATOM), 'command' => 'contao:zotero:sync'],
                $apiLogMetadata
            ));
        }

        try {
            return $this->doSync($libraryId, $onlyPublished);
        } finally {
            if ($apiLogPath !== null && $apiLogPath !== '') {
                $this->apiLogCollector->flush();
            }
        }
    }

    private function doSync(?int $libraryId, bool $onlyPublished): array
    {
        $libraries = $this->fetchLibraries($libraryId, $onlyPublished);
        $total = [
            'collections_created' => 0,
            'collections_updated' => 0,
            'collections_deleted' => 0,
            'collections_skipped' => 0,
            'collections_created_details' => [],
            'collections_updated_details' => [],
            'collections_deleted_details' => [],
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'items_skipped' => 0,
            'skipped_items' => [],
            'items_updated_details' => [],
            'items_deleted_details' => [],
            'items_created_details' => [],
            'attachments_created' => 0,
            'attachments_updated' => 0,
            'attachments_deleted' => 0,
            'attachments_skipped' => 0,
            'attachments_updated_details' => [],
            'attachments_deleted_details' => [],
            'attachments_created_details' => [],
            'collection_items_created' => 0,
            'collection_items_deleted' => 0,
            'collection_items_skipped' => 0,
            'collection_items_created_details' => [],
            'collection_items_deleted_details' => [],
            'skipped_collections' => [],
            'item_creators_created' => 0,
            'item_creators_deleted' => 0,
            'item_creators_skipped' => 0,
            'item_creators_created_details' => [],
            'item_creators_deleted_details' => [],
            'errors' => [],
        ];

        foreach ($libraries as $library) {
            try {
                $result = $this->syncLibrary($library);
                $total['collections_created'] += $result['collections_created'];
                $total['collections_updated'] += $result['collections_updated'];
                $total['collections_deleted'] += $result['collections_deleted'] ?? 0;
                $total['collections_skipped'] += $result['collections_skipped'] ?? 0;
                foreach ($result['collections_created_details'] ?? [] as $d) {
                    $total['collections_created_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['collections_updated_details'] ?? [] as $d) {
                    $total['collections_updated_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['collections_deleted_details'] ?? [] as $d) {
                    $total['collections_deleted_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['skipped_collections'] ?? [] as $d) {
                    $total['skipped_collections'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                $total['items_created'] += $result['items_created'];
                $total['items_updated'] += $result['items_updated'];
                $total['items_deleted'] += $result['items_deleted'];
                $total['items_skipped'] += $result['items_skipped'];
                foreach ($result['items_updated_details'] ?? [] as $d) {
                    $total['items_updated_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['items_deleted_details'] ?? [] as $d) {
                    $total['items_deleted_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['items_created_details'] ?? [] as $d) {
                    $total['items_created_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                $total['attachments_created'] += $result['attachments_created'] ?? 0;
                $total['attachments_updated'] += $result['attachments_updated'] ?? 0;
                $total['attachments_deleted'] += $result['attachments_deleted'] ?? 0;
                $total['attachments_skipped'] += $result['attachments_skipped'] ?? 0;
                foreach ($result['attachments_updated_details'] ?? [] as $d) {
                    $total['attachments_updated_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['attachments_deleted_details'] ?? [] as $d) {
                    $total['attachments_deleted_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['attachments_created_details'] ?? [] as $d) {
                    $total['attachments_created_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['skipped_items'] ?? [] as $skip) {
                    $total['skipped_items'][] = array_merge($skip, ['library' => $library['title'] ?? '']);
                }
                $total['collection_items_created'] += $result['collection_items_created'];
                $total['collection_items_deleted'] += $result['collection_items_deleted'];
                $total['collection_items_skipped'] += $result['collection_items_skipped'] ?? 0;
                foreach ($result['collection_items_created_details'] ?? [] as $d) {
                    $total['collection_items_created_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['collection_items_deleted_details'] ?? [] as $d) {
                    $total['collection_items_deleted_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                $total['item_creators_created'] += $result['item_creators_created'];
                $total['item_creators_deleted'] += $result['item_creators_deleted'];
                $total['item_creators_skipped'] += $result['item_creators_skipped'] ?? 0;
                foreach ($result['item_creators_created_details'] ?? [] as $d) {
                    $total['item_creators_created_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
                foreach ($result['item_creators_deleted_details'] ?? [] as $d) {
                    $total['item_creators_deleted_details'][] = array_merge($d, ['library' => $library['title'] ?? '']);
                }
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
     * @return array{collections_created: int, collections_updated: int, collections_deleted: int, collections_skipped: int, items_created: int, items_updated: int, items_deleted: int, items_skipped: int, skipped_items: array, attachments_created: int, attachments_updated: int, attachments_deleted: int, attachments_skipped: int, collection_items_created: int, collection_items_deleted: int, collection_items_skipped: int, item_creators_created: int, item_creators_deleted: int, item_creators_skipped: int}
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
            'collections_deleted' => 0,
            'collections_skipped' => 0,
            'collections_created_details' => [],
            'collections_updated_details' => [],
            'collections_deleted_details' => [],
            'items_created' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
            'items_skipped' => 0,
            'skipped_items' => [],
            'items_updated_details' => [],
            'items_deleted_details' => [],
            'items_created_details' => [],
            'attachments_created' => 0,
            'attachments_updated' => 0,
            'attachments_deleted' => 0,
            'attachments_skipped' => 0,
            'attachments_updated_details' => [],
            'attachments_deleted_details' => [],
            'attachments_created_details' => [],
            'collection_items_created' => 0,
            'collection_items_deleted' => 0,
            'collection_items_skipped' => 0,
            'collection_items_created_details' => [],
            'collection_items_deleted_details' => [],
            'skipped_collections' => [],
            'item_creators_created' => 0,
            'item_creators_deleted' => 0,
            'item_creators_skipped' => 0,
            'item_creators_created_details' => [],
            'item_creators_deleted_details' => [],
        ];

        // 1) Zuerst GET /deleted?since= – gelöschte Collections/Items holen und lokal entfernen
        //    (vor syncCollections, damit gelöschte Objekte nicht versehentlich wieder angelegt werden)
        $deletedObjects = $this->fetchDeletedObjects($prefix, $apiKey, $lastVersion);
        $this->syncDeletedCollections($pid, $deletedObjects, $result);
        $this->syncDeletedItems($pid, $deletedObjects, $result);

        // 2) Collections (inkl. Löschen nicht mehr in API vorhandener; überspringt Keys aus /deleted)
        $collectionKeyToId = $this->syncCollections($prefix, $apiKey, $pid, $result, $deletedObjects);

        // 3) Items (mit since für inkrementell; bei 0 lokalen Items Vollabzug)
        $localItemCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_zotero_item WHERE pid = ?', [$pid]);
        $effectiveSince = $localItemCount > 0 ? $lastVersion : 0;
        $itemKeyToId = $this->syncItems($prefix, $apiKey, $pid, $citationStyle, $citationLocale, $effectiveSince, $deletedObjects, $result);

        // 4) Collection-Item-Verknüpfungen
        $this->syncCollectionItems($prefix, $apiKey, $pid, $collectionKeyToId, $itemKeyToId, $result);

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
     * @param array{collections: list<string>, items: list<string>} $deletedObjects Von fetchDeletedObjects()
     * @return array<string, int> zotero_key -> unsere collection id
     */
    private function syncCollections(string $prefix, string $apiKey, int $pid, array &$result, array $deletedObjects = []): array
    {
        $deletedCollectionKeys = array_flip($deletedObjects['collections'] ?? []);

        $path = $prefix . '/collections';
        $keyToId = [];
        $keyToParentKey = [];

        $start = 0;
        $limit = 100;
        $noCacheHeaders = ['Cache-Control' => 'no-cache, no-store', 'Pragma' => 'no-cache'];
        do {
            $response = $this->zoteroClient->get($path, $apiKey, ['start' => $start, 'limit' => $limit, 'includeTrashed' => 1], ['headers' => $noCacheHeaders]);
            $this->ensureSuccessResponse($response, $path);
            $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
            $json = $response->getContent(false);
            $collections = $this->decodeJson($json, $path);
            if (!\is_array($collections)) {
                break;
            }
            foreach ($collections as $c) {
                $key = $c['key'] ?? '';
                // In /deleted gelistete Collections nicht importieren
                if (isset($deletedCollectionKeys[$key])) {
                    $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
                    $result['skipped_collections'][] = ['key' => $key, 'reason' => 'Objekt bereits gelöscht'];
                    continue;
                }
                $data = $c['data'] ?? [];
                $name = $data['name'] ?? '';
                $inTrash = (int) ($data['deleted'] ?? 0) === 1;
                $parentKey = $data['parentCollection'] ?? false;
                if ($parentKey === false) {
                    $parentKey = '';
                }
                $keyToParentKey[$key] = (string) $parentKey;

                $existing = $this->connection->fetchAssociative(
                    'SELECT id, title, published FROM tl_zotero_collection WHERE pid = ? AND zotero_key = ?',
                    [$pid, $key]
                );

                if ($existing !== false && \is_array($existing)) {
                    $updates = [];
                    $currentPublished = (string) ($existing['published'] ?? '1');
                    $targetPublished = $inTrash ? '0' : '1';
                    if ($currentPublished !== $targetPublished) {
                        $updates['published'] = $targetPublished;
                    }
                    if ((string) $existing['title'] !== $name) {
                        $updates['title'] = $name;
                    }

                    if ($updates !== []) {
                        try {
                            $updates['tstamp'] = time();
                            $this->connection->update('tl_zotero_collection', $updates, ['id' => $existing['id']]);
                            $result['collections_updated']++;
                            $result['collections_updated_details'][] = ['key' => $key, 'title' => $name, 'title_old' => (string) $existing['title']];
                        } catch (\Throwable $e) {
                            $this->logger->warning('Collection-Update übersprungen', ['key' => $key, 'reason' => $e->getMessage()]);
                            $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
                        }
                    }

                    $keyToId[$key] = (int) $existing['id'];
                } else {
                    try {
                        $this->connection->insert('tl_zotero_collection', [
                            'pid' => $pid,
                            'tstamp' => time(),
                            'parent_id' => null,
                            'sorting' => 0,
                            'zotero_key' => $key,
                            'title' => $name,
                            'published' => $inTrash ? '0' : '1',
                        ]);
                        $keyToId[$key] = (int) $this->connection->lastInsertId();
                        $result['collections_created']++;
                        $result['collections_created_details'][] = ['key' => $key, 'title' => $name];
                    } catch (\Throwable $e) {
                        $this->logger->warning('Collection-Erstellung übersprungen', ['key' => $key, 'reason' => $e->getMessage()]);
                        $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
                    }
                }
            }
            $start += $limit;
        } while (\count($collections) === $limit);

        // Parent-IDs setzen (zweiter Durchlauf)
        foreach ($keyToParentKey as $key => $parentKey) {
            if ($parentKey === '' || !isset($keyToId[$key], $keyToId[$parentKey])) {
                continue;
            }
            try {
                $this->connection->update('tl_zotero_collection', [
                    'parent_id' => $keyToId[$parentKey],
                ], ['id' => $keyToId[$key]]);
            } catch (\Throwable $e) {
                $this->logger->warning('Collection parent_id Update übersprungen', ['key' => $key, 'reason' => $e->getMessage()]);
                $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
            }
        }

        // Collections löschen, die in der API nicht mehr vorhanden sind (kein /collections/trash)
        $dbCollections = $this->connection->fetchAllAssociative(
            'SELECT id, zotero_key, title, parent_id FROM tl_zotero_collection WHERE pid = ?',
            [$pid]
        );
        $apiKeys = array_keys($keyToId);
        foreach ($dbCollections as $row) {
            $collKey = $row['zotero_key'] ?? '';
            if (\in_array($collKey, $apiKeys, true)) {
                continue;
            }
            try {
                $collId = (int) $row['id'];
                $newParentId = isset($row['parent_id']) && $row['parent_id'] !== '' ? (int) $row['parent_id'] : null;
                $this->connection->executeStatement(
                    'UPDATE tl_zotero_collection SET parent_id = ? WHERE parent_id = ?',
                    [$newParentId, $collId]
                );
                $this->connection->delete('tl_zotero_collection_item', ['collection_id' => $collId]);
                $this->connection->delete('tl_zotero_collection', ['id' => $collId]);
                $result['collections_deleted']++;
                $result['collections_deleted_details'][] = [
                    'key' => $collKey,
                    'title' => (string) ($row['title'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('Collection-Löschung übersprungen', ['key' => $collKey, 'reason' => $e->getMessage()]);
                $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
            }
        }

        return $keyToId;
    }

    /**
     * Holt alle seit lastVersion gelöschten Objekte (Collections, Items, Searches, Tags).
     * Ein Request statt separater Abrufe für Collections und Items.
     *
     * @return array{collections: list<string>, items: list<string>, searches: list<string>, tags: list<string>}
     * @see https://www.zotero.org/support/dev/web_api/v3/syncing
     */
    private function fetchDeletedObjects(string $prefix, string $apiKey, int $lastVersion): array
    {
        $path = $prefix . '/deleted';
        $noCacheHeaders = ['Cache-Control' => 'no-cache, no-store', 'Pragma' => 'no-cache'];
        $response = $this->zoteroClient->get($path, $apiKey, ['since' => $lastVersion], ['headers' => $noCacheHeaders]);
        $this->ensureSuccessResponse($response, $path);
        $this->lastModifiedVersion = $this->parseLastModifiedVersion($response);
        $decoded = $this->decodeJson($response->getContent(false), $path);

        return [
            'collections' => $this->normalizeDeletedKeys($decoded['collections'] ?? null),
            'items' => $this->normalizeDeletedKeys($decoded['items'] ?? null),
            'searches' => $this->normalizeDeletedKeys($decoded['searches'] ?? null),
            'tags' => $this->normalizeDeletedKeys($decoded['tags'] ?? null),
        ];
    }

    /**
     * Normalisiert Keys aus /deleted: API liefert entweder ["key1","key2"] oder {"key1":ver,"key2":ver}.
     *
     * @param mixed $data Array oder Objekt aus JSON
     * @return list<string>
     */
    private function normalizeDeletedKeys(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }
        $keys = array_keys($data);
        $numericKeys = array_filter($keys, 'is_numeric');
        if ($numericKeys === $keys) {
            return array_values(array_map('strval', $data));
        }
        return array_values(array_map('strval', $keys));
    }

    /**
     * Explizit in Zotero gelöschte Collections depublizieren (published=0).
     * Nutzt das von fetchDeletedObjects gelieferte Arrays. Collection-Item-Verknüpfungen bleiben erhalten (bei Wiederherstellung in Zotero sind die Items wieder zugeordnet).
     */
    private function syncDeletedCollections(int $pid, array $deletedObjects, array &$result): void
    {
        $deletedKeys = $deletedObjects['collections'] ?? [];
        foreach ($deletedKeys as $collKey) {
            $collKey = (string) $collKey;
            if ($collKey === '') {
                continue;
            }
            $row = $this->connection->fetchAssociative(
                'SELECT id, zotero_key, title FROM tl_zotero_collection WHERE pid = ? AND zotero_key = ?',
                [$pid, $collKey]
            );
            if ($row === false || !\is_array($row)) {
                continue;
            }
            try {
                $collId = (int) $row['id'];
                $this->connection->update('tl_zotero_collection', ['published' => '0'], ['id' => $collId]);
                $result['collections_deleted']++;
                $result['collections_deleted_details'][] = [
                    'key' => $collKey,
                    'title' => (string) ($row['title'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('Collection-Depublikation (deleted) übersprungen', ['key' => $collKey, 'reason' => $e->getMessage()]);
                $result['collections_skipped'] = ($result['collections_skipped'] ?? 0) + 1;
            }
        }
    }

    /**
     * Explizit in Zotero gelöschte Items und Attachments lokal entfernen.
     * Nutzt das von fetchDeletedObjects gelieferte items-Array (inkl. Attachments).
     */
    private function syncDeletedItems(int $pid, array $deletedObjects, array &$result): void
    {
        $deletedKeys = $deletedObjects['items'] ?? [];
        foreach ($deletedKeys as $itemKey) {
            $itemKey = (string) $itemKey;
            if ($itemKey === '') {
                continue;
            }

            try {
                // Zuerst prüfen, ob es ein Attachment ist (in tl_zotero_item_attachment)
                $attachmentRow = $this->connection->fetchAssociative(
                    'SELECT a.id, a.title FROM tl_zotero_item_attachment a
                    INNER JOIN tl_zotero_item i ON a.pid = i.id
                    WHERE i.pid = ? AND a.zotero_key = ?',
                    [$pid, $itemKey]
                );
                if ($attachmentRow !== false && \is_array($attachmentRow)) {
                    $this->connection->delete('tl_zotero_item_attachment', ['id' => (int) $attachmentRow['id']]);
                    $result['attachments_deleted']++;
                    $result['attachments_deleted_details'][] = [
                        'key' => $itemKey,
                        'title' => (string) ($attachmentRow['title'] ?? ''),
                    ];
                    continue;
                }

                // Sonst: reguläres Item (tl_zotero_item)
                $existing = $this->connection->fetchAssociative(
                    'SELECT id, title, item_type FROM tl_zotero_item WHERE pid = ? AND zotero_key = ?',
                    [$pid, $itemKey]
                );
                if ($existing === false || !\is_array($existing)) {
                    continue;
                }
                $itemId = (int) $existing['id'];
                $this->connection->delete('tl_zotero_collection_item', ['item_id' => $itemId]);
                $this->connection->delete('tl_zotero_item_creator', ['item_id' => $itemId]);
                $this->connection->delete('tl_zotero_item_attachment', ['pid' => $itemId]);
                $this->connection->delete('tl_zotero_item', ['id' => $itemId]);
                $result['items_deleted']++;
                $result['items_deleted_details'][] = [
                    'key' => $itemKey,
                    'item_type' => (string) ($existing['item_type'] ?? ''),
                    'title' => (string) ($existing['title'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $this->recordSkipped($result, $itemKey, 'Grund unbekannt', null, 'unknown');
            }
        }
    }

    /**
     * Items pro Seite gebündelt laden (2 Requests pro Seite statt 2 pro Item).
     *
     * @param array{collections: list<string>, items: list<string>} $deletedObjects Von fetchDeletedObjects()
     * @return array<string, int> zotero_key -> unsere item id
     */
    private function syncItems(string $prefix, string $apiKey, int $pid, string $citationStyle, string $citationLocale, int $since, array $deletedObjects, array &$result): array
    {
        $keyToId = [];
        $path = $prefix . '/items';

        $noCacheHeaders = ['Cache-Control' => 'no-cache, no-store', 'Pragma' => 'no-cache'];
        $deletedItemKeys = array_flip($deletedObjects['items'] ?? []);

        // Pass 1: Alle Nicht-Attachments (itemType=-attachment), damit Parents vor Attachments in keyToId stehen
        $start = 0;
        $limit = self::ITEMS_PAGE_SIZE;
        do {
            $query = ['start' => $start, 'limit' => $limit, 'include' => 'data', 'itemType' => '-attachment', 'includeTrashed' => 1];
            if ($since > 0) {
                $query['since'] = $since;
            }

            $response = $this->zoteroClient->get($path, $apiKey, $query, ['headers' => $noCacheHeaders]);
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
                if (isset($deletedItemKeys[$key])) {
                    $this->recordSkipped($result, $key, 'Objekt bereits gelöscht', null, (string) (($item['data'] ?? [])['itemType'] ?? 'unknown'));
                    continue;
                }
                try {
                    $bibContent = $this->fetchBibtexForItem($path, $apiKey, $key);
                    $citeContent = $this->fetchCiteForItem($path, $apiKey, $key, $citationStyle, $citationLocale);
                    $itemId = $this->upsertItemFromData($pid, $item, $bibContent, $citeContent, $result);
                    if ($itemId > 0) {
                        $keyToId[$key] = $itemId;
                        $this->syncItemCreatorsForItem($itemId, ($item['data'] ?? [])['creators'] ?? [], $result);
                    }
                } catch (\Throwable $e) {
                    $this->recordSkipped($result, $key, 'Grund unbekannt', null, (string) (($item['data'] ?? [])['itemType'] ?? 'unknown'));
                }
            }
            $start += $limit;
        } while (\count($items) === $limit);

        // Pass 2: Alle Attachments (itemType=attachment)
        // Zuerst alle Attachments sammeln, dann in Runden verarbeiten – damit auch Attachments
        // mit Parent-Attachment (z. B. Notiz an PDF) korrekt verarbeitet werden (Pagination-Reihenfolge).
        $allAttachments = [];
        $start = 0;
        do {
            $query = ['start' => $start, 'limit' => $limit, 'include' => 'data', 'itemType' => 'attachment', 'includeTrashed' => 1];
            if ($since > 0) {
                $query['since'] = $since;
            }

            $response = $this->zoteroClient->get($path, $apiKey, $query, ['headers' => $noCacheHeaders]);
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
                if (isset($deletedItemKeys[$key])) {
                    $this->recordSkipped($result, $key, 'Objekt bereits gelöscht', (string) (($item['data'] ?? [])['parentItem'] ?? ''), 'attachment');
                    continue;
                }
                $allAttachments[] = $item;
            }
            $start += $limit;
        } while (\count($items) === $limit);

        // Attachments topologisch sortieren: Eltern vor Kindern (parentItem = anderes Attachment)
        $allAttachments = $this->sortAttachmentsByParentChild($allAttachments, $keyToId);

        $attachmentKeyToPid = [];
        foreach ($allAttachments as $item) {
            $key = $item['key'] ?? '';
            if ($key === '') {
                continue;
            }
            try {
                $this->upsertAttachmentFromData($pid, $item, $keyToId, $attachmentKeyToPid, $deletedItemKeys, $result);
            } catch (\Throwable $e) {
                $this->recordSkipped($result, $key, 'Grund unbekannt', (string) (($item['data'] ?? [])['parentItem'] ?? ''), 'attachment');
            }
        }

        return $keyToId;
    }

    /**
     * Sortiert Attachments so, dass Eltern vor Kindern kommen (Parent-Attachment vor Child-Attachment).
     * Ermöglicht Verarbeitung in einer Runde ohne Pagination-Reihenfolge-Probleme.
     *
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, int>               $keyToId    Reguläre Items (Parent kann tl_zotero_item sein)
     * @return array<int, array<string, mixed>>
     */
    private function sortAttachmentsByParentChild(array $attachments, array $keyToId): array
    {
        $byKey = [];
        foreach ($attachments as $item) {
            $key = $item['key'] ?? '';
            if ($key !== '') {
                $byKey[$key] = $item;
            }
        }
        $attachmentKeys = array_keys($byKey);
        $sorted = [];
        $added = [];

        while (\count($sorted) < \count($attachmentKeys)) {
            $roundAdded = 0;
            foreach ($attachmentKeys as $key) {
                if (isset($added[$key])) {
                    continue;
                }
                $item = $byKey[$key] ?? null;
                if ($item === null) {
                    continue;
                }
                $parentKey = (string) (($item['data'] ?? [])['parentItem'] ?? '');
                if ($parentKey === '') {
                    $sorted[] = $item;
                    $added[$key] = true;
                    ++$roundAdded;
                    continue;
                }
                $parentReady = isset($keyToId[$parentKey]) || isset($added[$parentKey]);
                if ($parentReady) {
                    $sorted[] = $item;
                    $added[$key] = true;
                    ++$roundAdded;
                }
            }
            if ($roundAdded === 0) {
                break;
            }
        }

        $remaining = array_filter($attachmentKeys, static fn (string $k) => !isset($added[$k]));
        foreach ($remaining as $key) {
            $item = $byKey[$key] ?? null;
            if ($item !== null) {
                $sorted[] = $item;
            }
        }

        return $sorted;
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
     * @param int                $pid                   Library-ID (tl_zotero_library.id)
     * @param array<string,mixed> $item                  Zotero-Item mit key, version, data
     * @param array<string,int>  $keyToId               zotero_key -> tl_zotero_item.id (reguläre Items)
     * @param array<string,int>  $attachmentKeyToPid    zotero_key -> tl_zotero_item.id (bereits verarbeitete Attachments, Parent nutzt deren pid)
     * @param array<string, int> $deletedItemKeys       Key => 1 für in /deleted gelistete Items
     * @return bool true wenn verarbeitet, false wenn übersprungen (Parent nicht gefunden)
     */
    private function upsertAttachmentFromData(int $pid, array $item, array $keyToId, array &$attachmentKeyToPid, array $deletedItemKeys, array &$result): bool
    {
        $key = (string) ($item['key'] ?? '');
        $data = $item['data'] ?? [];
        $version = (int) ($item['version'] ?? 0);
        $parentKey = (string) ($data['parentItem'] ?? '');
        $inTrash = (int) ($data['deleted'] ?? 0) === 1;

        if ($parentKey === '') {
            $this->recordSkipped($result, $key, 'Grund unbekannt', null, 'attachment');

            return false;
        }

        // Parent-Item-ID: keyToId (reguläres Item), attachmentKeyToPid (Parent-Attachment dieses Laufs), sonst DB
        $parentId = $keyToId[$parentKey] ?? $attachmentKeyToPid[$parentKey] ?? null;
        if ($parentId === null) {
            // Reguläres Item in tl_zotero_item
            $found = $this->connection->fetchOne(
                'SELECT id FROM tl_zotero_item WHERE pid = ? AND zotero_key = ?',
                [$pid, $parentKey]
            );
            if ($found !== false) {
                $parentId = (int) $found;
            } else {
                // Parent-Attachment aus vorherigem Sync (a.pid = tl_zotero_item.id des übergeordneten Items)
                $found = $this->connection->fetchOne(
                    'SELECT a.pid FROM tl_zotero_item_attachment a INNER JOIN tl_zotero_item i ON i.id = a.pid WHERE i.pid = ? AND a.zotero_key = ?',
                    [$pid, $parentKey]
                );
                if ($found !== false) {
                    $parentId = (int) $found;
                }
            }
            if ($parentId === null) {
                $reason = isset($deletedItemKeys[$parentKey]) ? 'Parent wurde gelöscht' : 'Grund unbekannt';
                $this->recordSkipped($result, $key, $reason, $parentKey, 'attachment');

                return false;
            }
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
            'published' => $inTrash ? '0' : '1',
        ];

        if ($existing !== false) {
            unset($row['pid'], $row['sorting']);
            $this->connection->update('tl_zotero_item_attachment', $row, ['id' => $existing]);
            $result['attachments_updated']++;
            $result['attachments_updated_details'][] = [
                'key' => $key,
                'parent_key' => $parentKey,
                'title' => (string) ($data['title'] ?? ''),
            ];
        } else {
            $this->connection->insert('tl_zotero_item_attachment', $row);
            $result['attachments_created']++;
            $result['attachments_created_details'][] = [
                'key' => $key,
                'parent_key' => $parentKey,
                'title' => (string) ($data['title'] ?? ''),
            ];
        }

        $attachmentKeyToPid[$key] = $parentId;

        return true;
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
        $inTrash = (int) ($data['deleted'] ?? 0) === 1;
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
            'published' => $inTrash ? '0' : '1',
            'alias' => $alias,
        ];

        if ($existing !== false) {
            unset($row['pid']);
            $this->connection->update('tl_zotero_item', $row, ['id' => $existing]);
            $result['items_updated']++;
            $result['items_updated_details'][] = ['key' => $key, 'title' => (string) $title];
            return (int) $existing;
        }
        $this->connection->insert('tl_zotero_item', $row);
        $result['items_created']++;
        $result['items_created_details'][] = ['key' => $key, 'title' => (string) $title, 'item_type' => (string) $itemType];
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Collection-Item-Verknüpfungen synchronisieren. Entfernt Verknüpfungen, die nicht mehr in der Collection sind.
     *
     * Hinweis: itemKeyToId enthält bei inkrementellem Sync nur Keys von Items, die im aktuellen Lauf geholt wurden.
     * Items, die lokal existieren aber unverändert sind, fehlen dort. Deshalb DB-Lookup als Fallback.
     *
     * @param array<string, int> $collectionKeyToId
     * @param array<string, int> $itemKeyToId
     * @param array{collection_items_created: int, collection_items_deleted: int} $result
     */
    private function syncCollectionItems(string $prefix, string $apiKey, int $pid, array $collectionKeyToId, array $itemKeyToId, array &$result): void
    {
        $limit = 100;

        foreach ($collectionKeyToId as $collKey => $collectionId) {
            $path = $prefix . '/collections/' . $collKey . '/items';
            $items = [];
            $start = 0;
            do {
                $response = $this->zoteroClient->get($path, $apiKey, ['start' => $start, 'limit' => $limit]);
                $this->ensureSuccessResponse($response, $path);
                $page = $this->decodeJson($response->getContent(false), $path);
                if (!\is_array($page)) {
                    $items = null;
                    break;
                }
                $items = array_merge($items, $page);
                $start += $limit;
            } while (\count($page) >= $limit);

            if ($items === null) {
                try {
                    $deleted = $this->connection->delete('tl_zotero_collection_item', ['collection_id' => $collectionId]);
                    $result['collection_items_deleted'] += $deleted;
                } catch (\Throwable $e) {
                    $this->logger->warning('Collection-Item-Löschung übersprungen', ['collection_id' => $collectionId, 'reason' => $e->getMessage()]);
                    $result['collection_items_skipped'] = ($result['collection_items_skipped'] ?? 0) + 1;
                }
                continue;
            }

            // 1) Erwartete Item-IDs aus der API sammeln (alle Seiten)
            // itemKeyToId enthält bei inkrementellem Sync nur geänderte Items; unveränderte lokal vorhandene Items per DB nachschlagen
            $expectedItemIds = [];
            foreach ($items as $item) {
                $itemKey = $item['key'] ?? trim((string) $item);
                if ($itemKey === '') {
                    continue;
                }
                $itemId = $itemKeyToId[$itemKey] ?? null;
                if ($itemId === null) {
                    $found = $this->connection->fetchOne(
                        'SELECT id FROM tl_zotero_item WHERE pid = ? AND zotero_key = ?',
                        [$pid, $itemKey]
                    );
                    $itemId = $found !== false ? (int) $found : null;
                }
                if ($itemId !== null) {
                    $expectedItemIds[] = $itemId;
                }
            }

            // 2) Bestehende Verknüpfungen für diese Collection holen
            $existingLinks = $this->connection->fetchAllAssociative(
                'SELECT item_id FROM tl_zotero_collection_item WHERE collection_id = ?',
                [$collectionId]
            );
            $existingItemIds = array_map(static fn (array $row) => (int) $row['item_id'], $existingLinks);

            // 3) Verknüpfungen löschen, die nicht mehr erwartet werden
            // Depublizierte Items (Papierkorb) nicht entfernen – Zotero liefert sie evtl. nicht in /collections/{key}/items; bei Wiederherstellung sind sie wieder in der Collection
            $toDelete = array_diff($existingItemIds, $expectedItemIds);
            foreach ($toDelete as $itemId) {
                $itemRow = $this->connection->fetchAssociative('SELECT zotero_key, title, published FROM tl_zotero_item WHERE id = ?', [$itemId]);
                if (\is_array($itemRow) && ((string) ($itemRow['published'] ?? '1')) === '0') {
                    continue;
                }
                try {
                    $detail = ['collection_id' => $collectionId, 'item_id' => $itemId];
                    if (\is_array($itemRow)) {
                        $detail['item_key'] = (string) ($itemRow['zotero_key'] ?? '');
                        $detail['item_title'] = (string) ($itemRow['title'] ?? '');
                    }
                    $collRow = $this->connection->fetchAssociative('SELECT zotero_key, title FROM tl_zotero_collection WHERE id = ?', [$collectionId]);
                    if (\is_array($collRow)) {
                        $detail['collection_key'] = (string) ($collRow['zotero_key'] ?? '');
                        $detail['collection_title'] = (string) ($collRow['title'] ?? '');
                    }
                    $deleted = $this->connection->delete('tl_zotero_collection_item', [
                        'collection_id' => $collectionId,
                        'item_id' => $itemId,
                    ]);
                    if ($deleted > 0) {
                        $result['collection_items_deleted'] += $deleted;
                        $result['collection_items_deleted_details'][] = $detail;
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Collection-Item-Löschung übersprungen', ['collection_id' => $collectionId, 'item_id' => $itemId, 'reason' => $e->getMessage()]);
                    $result['collection_items_skipped'] = ($result['collection_items_skipped'] ?? 0) + 1;
                }
            }

            // 4) Neue Verknüpfungen anlegen (die noch nicht existieren)
            $toCreate = array_diff($expectedItemIds, $existingItemIds);
            $collRow = $this->connection->fetchAssociative('SELECT title FROM tl_zotero_collection WHERE id = ?', [$collectionId]);
            $collectionTitle = \is_array($collRow) ? (string) ($collRow['title'] ?? '') : '';
            $itemRowsById = [];
            if ($toCreate !== []) {
                $rows = $this->connection->fetchAllAssociative(
                    'SELECT id, zotero_key, title FROM tl_zotero_item WHERE id IN (' . implode(',', array_map('\intval', $toCreate)) . ')'
                );
                foreach ($rows as $r) {
                    $itemRowsById[(int) $r['id']] = $r;
                }
            }
            foreach ($toCreate as $itemId) {
                try {
                    $this->connection->insert('tl_zotero_collection_item', [
                        'pid' => $collectionId,
                        'tstamp' => time(),
                        'collection_id' => $collectionId,
                        'item_id' => $itemId,
                    ]);
                    $result['collection_items_created']++;
                    $detail = [
                        'collection_id' => $collectionId,
                        'collection_key' => $collKey,
                        'collection_title' => $collectionTitle,
                        'item_id' => $itemId,
                    ];
                    if (isset($itemRowsById[$itemId])) {
                        $detail['item_key'] = (string) ($itemRowsById[$itemId]['zotero_key'] ?? '');
                        $detail['item_title'] = (string) ($itemRowsById[$itemId]['title'] ?? '');
                    }
                    $result['collection_items_created_details'][] = $detail;
                } catch (\Throwable $e) {
                    $this->logger->warning('Collection-Item-Erstellung übersprungen', ['collection_id' => $collectionId, 'item_id' => $itemId, 'reason' => $e->getMessage()]);
                    $result['collection_items_skipped'] = ($result['collection_items_skipped'] ?? 0) + 1;
                }
            }
        }
    }

    /**
     * Item-Creator-Verknüpfungen für ein einzelnes Item synchronisieren (Daten kommen aus Item, kein eigener API-Endpoint).
     * Creator-Map-Einträge werden bei Bedarf angelegt (member_id=0).
     *
     * @param int   $itemId   tl_zotero_item.id
     * @param array $creators Zotero creators-Array aus item.data.creators
     */
    private function syncItemCreatorsForItem(int $itemId, array $creators, array &$result): void
    {
        try {
            $expectedCreatorMapIds = [];

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

            $existingLinks = $this->connection->fetchAllAssociative(
                'SELECT creator_map_id FROM tl_zotero_item_creator WHERE item_id = ?',
                [$itemId]
            );
            $existingCreatorMapIds = array_map(static fn (array $row) => (int) $row['creator_map_id'], $existingLinks);

            $itemRow = $this->connection->fetchAssociative('SELECT zotero_key, title FROM tl_zotero_item WHERE id = ?', [$itemId]);

            $toDelete = array_diff($existingCreatorMapIds, $expectedCreatorMapIds);
            foreach ($toDelete as $creatorMapId) {
                $creatorRow = $this->connection->fetchAssociative(
                    'SELECT zotero_firstname, zotero_lastname FROM tl_zotero_creator_map WHERE id = ?',
                    [$creatorMapId]
                );
                $deleted = $this->connection->delete('tl_zotero_item_creator', [
                    'item_id' => $itemId,
                    'creator_map_id' => $creatorMapId,
                ]);
                if ($deleted > 0) {
                    $result['item_creators_deleted'] += $deleted;
                    $detail = [
                        'item_id' => $itemId,
                        'item_key' => \is_array($itemRow) ? (string) ($itemRow['zotero_key'] ?? '') : '',
                        'item_title' => \is_array($itemRow) ? (string) ($itemRow['title'] ?? '') : '',
                        'creator_firstname' => \is_array($creatorRow) ? (string) ($creatorRow['zotero_firstname'] ?? '') : '',
                        'creator_lastname' => \is_array($creatorRow) ? (string) ($creatorRow['zotero_lastname'] ?? '') : '',
                    ];
                    $result['item_creators_deleted_details'][] = $detail;
                }
            }

            $toCreate = array_diff($expectedCreatorMapIds, $existingCreatorMapIds);
            foreach ($toCreate as $creatorMapId) {
                $creatorRow = $this->connection->fetchAssociative(
                    'SELECT zotero_firstname, zotero_lastname FROM tl_zotero_creator_map WHERE id = ?',
                    [$creatorMapId]
                );
                $this->connection->insert('tl_zotero_item_creator', [
                    'pid' => $itemId,
                    'tstamp' => time(),
                    'item_id' => $itemId,
                    'creator_map_id' => $creatorMapId,
                ]);
                $result['item_creators_created']++;
                $detail = [
                    'item_id' => $itemId,
                    'item_key' => \is_array($itemRow) ? (string) ($itemRow['zotero_key'] ?? '') : '',
                    'item_title' => \is_array($itemRow) ? (string) ($itemRow['title'] ?? '') : '',
                    'creator_firstname' => \is_array($creatorRow) ? (string) ($creatorRow['zotero_firstname'] ?? '') : '',
                    'creator_lastname' => \is_array($creatorRow) ? (string) ($creatorRow['zotero_lastname'] ?? '') : '',
                ];
                $result['item_creators_created_details'][] = $detail;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Item-Creator-Sync übersprungen', ['item_id' => $itemId, 'reason' => $e->getMessage()]);
            $result['item_creators_skipped'] = ($result['item_creators_skipped'] ?? 0) + 1;
        }
    }

    /**
     * Debug-Daten für Fehlersuche. Liefert Rohdaten aller genutzten API-Endpoints und lokalen Entitäten.
     *
     * API-Endpoints: /deleted, /collections, /items (Items + Attachments), /collections/{key}/items
     * Lokale Tabellen: tl_zotero_collection, tl_zotero_item, tl_zotero_item_attachment,
     *                  tl_zotero_collection_item, tl_zotero_item_creator
     *
     * @return array{library: array, deleted: array, collections_sample: array, items_sample: array, attachments_sample: array, collection_items_sample: array|null, local_collections: array, local_items: array, local_attachments: array, local_collection_items: array, local_item_creators: array}|null null wenn Library nicht gefunden
     */
    public function getDebugSyncData(int $libraryId): ?array
    {
        $library = $this->connection->fetchAssociative(
            'SELECT id, title, library_type, library_id, api_key, last_sync_version FROM tl_zotero_library WHERE id = ?',
            [$libraryId]
        );
        if (!$library || !\is_array($library)) {
            return null;
        }

        $prefix = $this->libraryPrefix($library);
        $apiKey = (string) ($library['api_key'] ?? '');
        $lastVersion = (int) ($library['last_sync_version'] ?? 0);

        // GET /deleted?since=
        $pathDeleted = $prefix . '/deleted';
        $responseDeleted = $this->zoteroClient->get($pathDeleted, $apiKey, ['since' => $lastVersion]);
        $decodedDeleted = json_decode($responseDeleted->getContent(false), true);

        // GET /collections
        $pathColl = $prefix . '/collections';
        $responseColl = $this->zoteroClient->get($pathColl, $apiKey, ['start' => 0, 'limit' => 10]);
        $collectionsSample = json_decode($responseColl->getContent(false), true);

        // GET /items (Items, nicht Attachments)
        $pathItems = $prefix . '/items';
        $responseItems = $this->zoteroClient->get($pathItems, $apiKey, ['start' => 0, 'limit' => 5, 'include' => 'data', 'itemType' => '-attachment']);
        $itemsSample = json_decode($responseItems->getContent(false), true);

        // GET /items (Attachments)
        $responseAttachments = $this->zoteroClient->get($pathItems, $apiKey, ['start' => 0, 'limit' => 5, 'include' => 'data', 'itemType' => 'attachment']);
        $attachmentsSample = json_decode($responseAttachments->getContent(false), true);

        // GET /collections/{key}/items für erste Collection (falls vorhanden)
        $collectionItemsSample = null;
        if (\is_array($collectionsSample) && isset($collectionsSample[0]['key'])) {
            $firstCollKey = $collectionsSample[0]['key'];
            $pathCollItems = $prefix . '/collections/' . $firstCollKey . '/items';
            $responseCollItems = $this->zoteroClient->get($pathCollItems, $apiKey, ['limit' => 10]);
            $collectionItemsSample = json_decode($responseCollItems->getContent(false), true);
        }

        // Lokale Entitäten
        $localCollections = $this->connection->fetchAllAssociative(
            'SELECT id, zotero_key, title, parent_id FROM tl_zotero_collection WHERE pid = ? ORDER BY id',
            [$libraryId]
        );
        $localItems = $this->connection->fetchAllAssociative(
            'SELECT id, zotero_key, title, item_type FROM tl_zotero_item WHERE pid = ? ORDER BY id LIMIT 15',
            [$libraryId]
        );
        $localAttachments = $this->connection->fetchAllAssociative(
            'SELECT a.id, a.zotero_key, a.title, a.pid as item_id, i.zotero_key as parent_key FROM tl_zotero_item_attachment a LEFT JOIN tl_zotero_item i ON i.id = a.pid WHERE i.pid = ? ORDER BY a.id LIMIT 10',
            [$libraryId]
        );
        $localCollectionItems = $this->connection->fetchAllAssociative(
            'SELECT ci.collection_id, ci.item_id, c.zotero_key as coll_key, c.title as coll_title, i.zotero_key as item_key, i.title as item_title FROM tl_zotero_collection_item ci JOIN tl_zotero_collection c ON c.id = ci.collection_id JOIN tl_zotero_item i ON i.id = ci.item_id WHERE c.pid = ? ORDER BY ci.collection_id, ci.item_id LIMIT 20',
            [$libraryId]
        );
        $localItemCreators = $this->connection->fetchAllAssociative(
            'SELECT ic.item_id, ic.creator_map_id, i.zotero_key, i.title, cm.zotero_firstname, cm.zotero_lastname FROM tl_zotero_item_creator ic JOIN tl_zotero_item i ON i.id = ic.item_id LEFT JOIN tl_zotero_creator_map cm ON cm.id = ic.creator_map_id WHERE i.pid = ? ORDER BY ic.item_id LIMIT 15',
            [$libraryId]
        );

        return [
            'library' => [
                'id' => $libraryId,
                'title' => $library['title'] ?? '',
                'prefix' => $prefix,
                'last_sync_version' => $lastVersion,
            ],
            'deleted' => [
                'http_status' => $responseDeleted->getStatusCode(),
                'last_modified_version' => $this->parseLastModifiedVersion($responseDeleted),
                'collections' => $decodedDeleted['collections'] ?? null,
                'items' => $decodedDeleted['items'] ?? null,
                'searches' => $decodedDeleted['searches'] ?? null,
                'tags' => $decodedDeleted['tags'] ?? null,
            ],
            'collections_sample' => \is_array($collectionsSample) ? $collectionsSample : [],
            'items_sample' => \is_array($itemsSample) ? $itemsSample : [],
            'attachments_sample' => \is_array($attachmentsSample) ? $attachmentsSample : [],
            'collection_items_sample' => \is_array($collectionItemsSample) ? $collectionItemsSample : null,
            'collection_items_sample_coll_key' => \is_array($collectionsSample) && isset($collectionsSample[0]['key']) ? $collectionsSample[0]['key'] : null,
            'local_collections' => \is_array($localCollections) ? $localCollections : [],
            'local_items' => \is_array($localItems) ? $localItems : [],
            'local_attachments' => \is_array($localAttachments) ? $localAttachments : [],
            'local_collection_items' => \is_array($localCollectionItems) ? $localCollectionItems : [],
            'local_item_creators' => \is_array($localItemCreators) ? $localItemCreators : [],
        ];
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
