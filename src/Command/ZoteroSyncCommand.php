<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Führt den Zotero-Sync aus (alle oder eine Library).
 * Liegt in src/Command/; Registrierung über services.yaml (console.command).
 */
#[AsCommand(
    name: 'contao:zotero:sync',
    description: 'Synchronisiert Zotero-Bibliotheken (Collections, Items) in die lokalen Tabellen.',
)]
final class ZoteroSyncCommand extends Command
{
    public function __construct(
        private readonly ZoteroSyncService $syncService,
        private readonly ZoteroLocaleService $localeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'library',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Nur diese Library-ID synchronisieren (ohne Option: alle)'
        );
        $this->addOption(
            'reset',
            'r',
            InputOption::VALUE_NONE,
            'Sync-Metadaten vor dem Abruf zurücksetzen (Vollabzug wie „Synchronisation zurücksetzen“ im Backend)'
        );
        $this->addOption(
            'log-skipped',
            null,
            InputOption::VALUE_REQUIRED,
            'Übersprungene Items in diese Datei schreiben (JSON, z. B. var/logs/zotero_skipped.json)'
        );
        $this->addOption(
            'log-changes',
            null,
            InputOption::VALUE_REQUIRED,
            'Erstellte, aktualisierte und gelöschte Items/Attachments/Collections in diese Datei schreiben (JSON, z. B. var/logs/zotero_changes.json)'
        );
        $this->addOption(
            'show-details',
            null,
            InputOption::VALUE_NONE,
            'Detail-Tabellen (übersprungene Items, erstellte/aktualisierte/gelöschte Einträge) anzeigen'
        );
        $this->addOption(
            'debug',
            null,
            InputOption::VALUE_NONE,
            'Debug-Info: Rohdaten aller API-Endpoints und lokalen Entitäten (erfordert -l/--library)'
        );
        $this->addOption(
            'log-api',
            null,
            InputOption::VALUE_REQUIRED,
            'Alle API-Aufrufe (Request, Header, Response) als JSON in diese Datei schreiben (z. B. var/logs/zotero_api.json)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $libraryId = $input->getOption('library');
        $id = null;
        if ($libraryId !== null && $libraryId !== '') {
            $id = (int) $libraryId;
            if ($id <= 0) {
                $io->error('Ungültige Library-ID.');

                return self::FAILURE;
            }
        }

        $debugMode = $input->getOption('debug');
        if ($debugMode && $id <= 0) {
            $io->error('Option --debug erfordert eine Library-ID (-l/--library).');

            return self::FAILURE;
        }
        if ($debugMode) {
            if (!$this->displayDebugInfo($io, (int) $id)) {
                return self::FAILURE;
            }
        }

        $resetFirst = $input->getOption('reset');
        if ($resetFirst) {
            if ($id > 0) {
                $this->syncService->resetSyncState($id);
                $io->note('Sync-Metadaten für Library-ID ' . $id . ' zurückgesetzt (Vollabzug).');
            } else {
                $this->syncService->resetAllSyncStates();
                $io->note('Sync-Metadaten für alle Libraries zurückgesetzt (Vollabzug).');
            }
        }

        $io->info($id > 0 ? 'Sync starten (Library-ID: ' . $id . ')' : 'Sync starten (alle Libraries).');

        $io->info('Locales werden aktualisiert …');
        $this->localeService->fetchAndStore();

        $logApiPath = $input->getOption('log-api');
        $apiLogMetadata = [];
        if ($logApiPath !== null && $logApiPath !== '') {
            $apiLogMetadata = [
                'options' => [
                    'library' => $id > 0 ? (string) $id : null,
                    'reset' => $resetFirst,
                ],
            ];
        }
        $result = $this->syncService->sync(
            $id > 0 ? $id : null,
            false,
            $logApiPath !== null && $logApiPath !== '' ? $logApiPath : null,
            $apiLogMetadata
        );

        $io->success('Sync beendet.');

        if ($logApiPath !== null && $logApiPath !== '') {
            $io->writeln(sprintf('API-Log in <info>%s</info> geschrieben.', $logApiPath));
        }

        $skippedItems = $result['skipped_items'] ?? [];
        $skippedCollections = $result['skipped_collections'] ?? [];
        $logSkippedPath = $input->getOption('log-skipped');
        if ($logSkippedPath !== null && $logSkippedPath !== '') {
            $this->writeSkippedLog($logSkippedPath, $skippedItems, $skippedCollections);
            $totalSkipped = \count($skippedItems) + \count($skippedCollections);
            $io->writeln(sprintf('Skipped-Log in <info>%s</info> geschrieben (%d Items, %d Collections).', $logSkippedPath, \count($skippedItems), \count($skippedCollections)));
        }
        $showDetails = $input->getOption('show-details');
        if ($showDetails && $skippedItems !== []) {
            $io->section('Übersprungene Items (protokolliert)');
            $skipRows = [];
            foreach ($skippedItems as $s) {
                $parent = isset($s['parent_key']) ? ' → Parent: ' . $s['parent_key'] : '';
                $skipRows[] = [
                    $s['key'] ?? '',
                    ($s['item_type'] ?? '') . $parent,
                    $s['reason'] ?? '',
                    $s['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Typ / Parent', 'Grund', 'Library'], $skipRows);
        }

        if ($result['errors'] !== []) {
            $io->warning('Fehler: ' . implode('; ', $result['errors']));
        }

        $logChangesPath = $input->getOption('log-changes');
        $changesPayload = $this->buildChangesPayload($result);
        if ($logChangesPath !== null && $logChangesPath !== '') {
            $this->writeChangesLog($logChangesPath, $changesPayload);
            $totalChanges = \count($changesPayload['items_created'] ?? [])
                + \count($changesPayload['items_updated'] ?? [])
                + \count($changesPayload['items_deleted'] ?? [])
                + \count($changesPayload['attachments_created'] ?? [])
                + \count($changesPayload['attachments_updated'] ?? [])
                + \count($changesPayload['attachments_deleted'] ?? [])
                + \count($changesPayload['collections_created'] ?? [])
                + \count($changesPayload['collections_updated'] ?? [])
                + \count($changesPayload['collections_deleted'] ?? [])
                + \count($changesPayload['collection_items_created'] ?? [])
                + \count($changesPayload['collection_items_deleted'] ?? [])
                + \count($changesPayload['item_creators_created'] ?? [])
                + \count($changesPayload['item_creators_deleted'] ?? []);
            $io->writeln(sprintf('Änderungs-Log in <info>%s</info> geschrieben (%d Einträge).', $logChangesPath, $totalChanges));
        }

        $skippedCollections = $result['skipped_collections'] ?? [];
        if ($showDetails && $skippedCollections !== []) {
            $io->section('Übersprungene Collections (bereits gelöscht)');
            $skipCollRows = [];
            foreach ($skippedCollections as $s) {
                $skipCollRows[] = [$s['key'] ?? '', $s['reason'] ?? '', $s['library'] ?? ''];
            }
            $io->table(['Key', 'Grund', 'Library'], $skipCollRows);
        }

        if ($showDetails) {
            $this->displayChangesTables($io, $result);
        }

        $rows = [
            ['Items erstellt', $result['items_created']],
            ['Items aktualisiert', $result['items_updated']],
            ['Items gelöscht', $result['items_deleted']],
            ['Items übersprungen', $result['items_skipped'] ?? 0],
            ['Item-Creator-Verknüpfungen erstellt', $result['item_creators_created'] ?? 0],
            ['Item-Creator-Verknüpfungen aktualisiert', $result['item_creators_updated'] ?? 0],
            ['Item-Creator-Verknüpfungen gelöscht', $result['item_creators_deleted'] ?? 0],
            ['Item-Creator-Verknüpfungen übersprungen', $result['item_creators_skipped'] ?? 0],
            ['Attachments erstellt', $result['attachments_created'] ?? 0],
            ['Attachments aktualisiert', $result['attachments_updated'] ?? 0],
            ['Attachments gelöscht', $result['attachments_deleted'] ?? 0],
            ['Attachments übersprungen', $result['attachments_skipped'] ?? 0],
            ['Collections erstellt', $result['collections_created']],
            ['Collections aktualisiert', $result['collections_updated']],
            ['Collections gelöscht', $result['collections_deleted'] ?? 0],
            ['Collections übersprungen', $result['collections_skipped'] ?? 0],
            ['Collection-Item-Verknüpfungen erstellt', $result['collection_items_created'] ?? 0],
            ['Collection-Item-Verknüpfungen aktualisiert', $result['collection_items_updated'] ?? 0],
            ['Collection-Item-Verknüpfungen gelöscht', $result['collection_items_deleted'] ?? 0],
            ['Collection-Item-Verknüpfungen übersprungen', $result['collection_items_skipped'] ?? 0],
        ];
        $io->section('Zusammenfassung');
        $io->table(['Metrik', 'Anzahl'], $rows);

        return self::SUCCESS;
    }

    /**
     * Zeigt Debug-Info (alle API-Endpoints und lokale Entitäten).
     *
     * @return bool false wenn Library nicht gefunden
     */
    private function displayDebugInfo(SymfonyStyle $io, int $libraryId): bool
    {
        $data = $this->syncService->getDebugSyncData($libraryId);
        if ($data === null) {
            $io->error('Library mit ID ' . $libraryId . ' nicht gefunden.');

            return false;
        }

        $lib = $data['library'];
        $io->title('Zotero Sync Debug – ' . $lib['title'] . ' (ID ' . $lib['id'] . ')');
        $io->writeln('Prefix: ' . $lib['prefix']);
        $io->writeln('last_sync_version: ' . $lib['last_sync_version']);
        $io->newLine();

        // GET /deleted
        $deleted = $data['deleted'];
        $io->section('GET /deleted?since=' . $lib['last_sync_version']);
        $io->writeln('HTTP Status: ' . ($deleted['http_status'] ?? 'n/a'));
        $io->writeln('Last-Modified-Version: ' . ($deleted['last_modified_version'] ?? 'n/a'));
        $colls = $deleted['collections'] ?? null;
        $items = $deleted['items'] ?? null;
        $searches = $deleted['searches'] ?? null;
        $tags = $deleted['tags'] ?? null;
        $io->writeln('collections (' . (\is_array($colls) ? \count($colls) : 'n/a') . '): ' . json_encode($colls, \JSON_UNESCAPED_UNICODE));
        $io->writeln('items (' . (\is_array($items) ? \count($items) : 'n/a') . '): ' . json_encode($items, \JSON_UNESCAPED_UNICODE));
        $io->writeln('searches (' . (\is_array($searches) ? \count($searches) : 'n/a') . '): ' . json_encode($searches, \JSON_UNESCAPED_UNICODE));
        $io->writeln('tags (' . (\is_array($tags) ? \count($tags) : 'n/a') . '): ' . json_encode($tags, \JSON_UNESCAPED_UNICODE));

        // GET /collections
        $collSample = $data['collections_sample'] ?? [];
        $collKeys = [];
        if (\is_array($collSample)) {
            foreach ($collSample as $c) {
                $collKeys[] = ($c['key'] ?? '') . ' (' . (($c['data'] ?? [])['name'] ?? '') . ')';
            }
        }
        $io->section('GET /collections (erste 10)');
        $io->writeln(\count($collKeys) > 0 ? implode(', ', $collKeys) : '(keine)');

        // GET /items (Items)
        $itemsSample = $data['items_sample'] ?? [];
        $itemKeys = [];
        if (\is_array($itemsSample)) {
            foreach ($itemsSample as $it) {
                $d = $it['data'] ?? [];
                $itemKeys[] = ($it['key'] ?? '') . ' [' . ($d['itemType'] ?? '') . '] "' . substr((string) ($d['title'] ?? ''), 0, 30) . '"';
            }
        }
        $io->section('GET /items?itemType=-attachment (erste 5)');
        $io->writeln(\count($itemKeys) > 0 ? implode("\n", $itemKeys) : '(keine)');

        // GET /items (Attachments)
        $attSample = $data['attachments_sample'] ?? [];
        $attKeys = [];
        if (\is_array($attSample)) {
            foreach ($attSample as $a) {
                $d = $a['data'] ?? [];
                $attKeys[] = ($a['key'] ?? '') . ' (parent: ' . ($d['parentItem'] ?? '-') . ') "' . substr((string) ($d['title'] ?? ''), 0, 25) . '"';
            }
        }
        $io->section('GET /items?itemType=attachment (erste 5)');
        $io->writeln(\count($attKeys) > 0 ? implode("\n", $attKeys) : '(keine)');

        // GET /collections/{key}/items
        $collItemsKey = $data['collection_items_sample_coll_key'] ?? null;
        $collItemsSample = $data['collection_items_sample'] ?? null;
        $io->section($collItemsKey ? 'GET /collections/' . $collItemsKey . '/items (erste 10)' : 'GET /collections/{key}/items');
        if (\is_array($collItemsSample) && $collItemsSample !== []) {
            $ciRows = [];
            foreach ($collItemsSample as $ci) {
                $key = \is_array($ci) ? ($ci['key'] ?? '') : (string) $ci;
                $title = \is_array($ci) && isset($ci['data']['title']) ? substr((string) $ci['data']['title'], 0, 35) : '';
                $ciRows[] = [$key, $title];
            }
            $io->table(['itemKey', 'title'], $ciRows);
        } else {
            $io->writeln($collItemsKey ? '(keine Items in dieser Collection)' : '(keine Collections)');
        }

        // Lokale Entitäten
        $localColls = $data['local_collections'] ?? [];
        $io->section('Lokale Collections (tl_zotero_collection, pid=' . $libraryId . ')');
        if ($localColls !== []) {
            $rows = [];
            foreach ($localColls as $r) {
                $rows[] = [$r['id'] ?? '', $r['zotero_key'] ?? '', $r['title'] ?? '', $r['parent_id'] ?? ''];
            }
            $io->table(['id', 'zotero_key', 'title', 'parent_id'], $rows);
        } else {
            $io->writeln('(keine)');
        }

        $localItems = $data['local_items'] ?? [];
        $io->section('Lokale Items (tl_zotero_item, erste 15)');
        if ($localItems !== []) {
            $rows = [];
            foreach ($localItems as $r) {
                $rows[] = [$r['id'] ?? '', $r['zotero_key'] ?? '', $r['item_type'] ?? '', substr((string) ($r['title'] ?? ''), 0, 40)];
            }
            $io->table(['id', 'zotero_key', 'item_type', 'title'], $rows);
        } else {
            $io->writeln('(keine)');
        }

        $localAttachments = $data['local_attachments'] ?? [];
        $io->section('Lokale Attachments (tl_zotero_item_attachment, erste 10)');
        if ($localAttachments !== []) {
            $rows = [];
            foreach ($localAttachments as $r) {
                $rows[] = [$r['id'] ?? '', $r['zotero_key'] ?? '', $r['title'] ?? '', $r['parent_key'] ?? $r['item_id'] ?? ''];
            }
            $io->table(['id', 'zotero_key', 'title', 'parent_key'], $rows);
        } else {
            $io->writeln('(keine)');
        }

        $localCollItems = $data['local_collection_items'] ?? [];
        $io->section('Lokale Collection-Item-Verknüpfungen (tl_zotero_collection_item, erste 20)');
        if ($localCollItems !== []) {
            $rows = [];
            foreach ($localCollItems as $r) {
                $rows[] = [$r['coll_key'] ?? '', $r['coll_title'] ?? '', $r['item_key'] ?? '', substr((string) ($r['item_title'] ?? ''), 0, 35)];
            }
            $io->table(['coll_key', 'coll_title', 'item_key', 'item_title'], $rows);
        } else {
            $io->writeln('(keine)');
        }

        $localCreators = $data['local_item_creators'] ?? [];
        $io->section('Lokale Item-Creator-Verknüpfungen (tl_zotero_item_creator + tl_zotero_creator_map, erste 15)');
        if ($localCreators !== []) {
            $rows = [];
            foreach ($localCreators as $r) {
                $name = trim(($r['zotero_firstname'] ?? '') . ' ' . ($r['zotero_lastname'] ?? ''));
                $rows[] = [$r['zotero_key'] ?? '', substr((string) ($r['title'] ?? ''), 0, 30), $name];
            }
            $io->table(['item_key', 'item_title', 'creator'], $rows);
        } else {
            $io->writeln('(keine)');
        }
        $io->newLine();

        return true;
    }

    /**
     * Baut das Payload-Objekt für das Änderungs-Log.
     */
    private function buildChangesPayload(array $result): array
    {
        return [
            'synced_at' => date(\DateTimeInterface::ATOM),
            'collections_created' => $result['collections_created_details'] ?? [],
            'collections_updated' => $result['collections_updated_details'] ?? [],
            'collections_deleted' => $result['collections_deleted_details'] ?? [],
            'items_created' => $result['items_created_details'] ?? [],
            'items_updated' => $result['items_updated_details'] ?? [],
            'items_deleted' => $result['items_deleted_details'] ?? [],
            'attachments_created' => $result['attachments_created_details'] ?? [],
            'attachments_updated' => $result['attachments_updated_details'] ?? [],
            'attachments_deleted' => $result['attachments_deleted_details'] ?? [],
            'collection_items_created' => $result['collection_items_created_details'] ?? [],
            'collection_items_deleted' => $result['collection_items_deleted_details'] ?? [],
            'item_creators_created' => $result['item_creators_created_details'] ?? [],
            'item_creators_deleted' => $result['item_creators_deleted_details'] ?? [],
        ];
    }

    /**
     * Schreibt das Änderungs-Log als JSON in die angegebene Datei.
     */
    private function writeChangesLog(string $path, array $payload): void
    {
        $json = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $json);
    }

    /**
     * Zeigt Tabellen mit aktualisierten/gelöschten Items an (wenn vorhanden).
     */
    private function displayChangesTables(SymfonyStyle $io, array $result): void
    {
        $collectionsCreated = $result['collections_created_details'] ?? [];
        if ($collectionsCreated !== []) {
            $io->section('Erstellte Collections');
            $rows = [];
            foreach ($collectionsCreated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Titel', 'Library'], $rows);
        }

        $collectionsUpdated = $result['collections_updated_details'] ?? [];
        if ($collectionsUpdated !== []) {
            $io->section('Aktualisierte Collections');
            $rows = [];
            foreach ($collectionsUpdated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['title_old'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Titel (alt)', 'Titel (neu)', 'Library'], $rows);
        }

        $collectionsDeleted = $result['collections_deleted_details'] ?? [];
        if ($collectionsDeleted !== []) {
            $io->section('Gelöschte Collections');
            $rows = [];
            foreach ($collectionsDeleted as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Titel', 'Library'], $rows);
        }

        $itemsCreated = $result['items_created_details'] ?? [];
        if ($itemsCreated !== []) {
            $io->section('Erstellte Items');
            $rows = [];
            foreach ($itemsCreated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['item_type'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Typ', 'Titel', 'Library'], $rows);
        }

        $itemsUpdated = $result['items_updated_details'] ?? [];
        if ($itemsUpdated !== []) {
            $io->section('Aktualisierte Items');
            $rows = [];
            foreach ($itemsUpdated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Titel', 'Library'], $rows);
        }

        $itemsDeleted = $result['items_deleted_details'] ?? [];
        if ($itemsDeleted !== []) {
            $io->section('Gelöschte Items (Trash)');
            $rows = [];
            foreach ($itemsDeleted as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['item_type'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Typ', 'Titel', 'Library'], $rows);
        }

        $attachmentsCreated = $result['attachments_created_details'] ?? [];
        if ($attachmentsCreated !== []) {
            $io->section('Erstellte Attachments');
            $rows = [];
            foreach ($attachmentsCreated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['parent_key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Parent', 'Titel', 'Library'], $rows);
        }

        $attachmentsUpdated = $result['attachments_updated_details'] ?? [];
        if ($attachmentsUpdated !== []) {
            $io->section('Aktualisierte Attachments');
            $rows = [];
            foreach ($attachmentsUpdated as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['parent_key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Parent', 'Titel', 'Library'], $rows);
        }

        $attachmentsDeleted = $result['attachments_deleted_details'] ?? [];
        if ($attachmentsDeleted !== []) {
            $io->section('Gelöschte Attachments (Trash)');
            $rows = [];
            foreach ($attachmentsDeleted as $d) {
                $rows[] = [
                    $d['key'] ?? '',
                    $d['title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Key', 'Titel', 'Library'], $rows);
        }

        $collectionItemsCreated = $result['collection_items_created_details'] ?? [];
        if ($collectionItemsCreated !== []) {
            $io->section('Erstellte Collection-Item-Verknüpfungen');
            $rows = [];
            foreach ($collectionItemsCreated as $d) {
                $rows[] = [
                    $d['collection_title'] ?? $d['collection_key'] ?? (string) ($d['collection_id'] ?? ''),
                    $d['item_key'] ?? (string) ($d['item_id'] ?? ''),
                    $d['item_title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Collection', 'Item-Key', 'Item-Titel', 'Library'], $rows);
        }

        $collectionItemsDeleted = $result['collection_items_deleted_details'] ?? [];
        if ($collectionItemsDeleted !== []) {
            $io->section('Entfernte Collection-Item-Verknüpfungen');
            $rows = [];
            foreach ($collectionItemsDeleted as $d) {
                $rows[] = [
                    $d['collection_title'] ?? $d['collection_key'] ?? (string) ($d['collection_id'] ?? ''),
                    $d['item_key'] ?? (string) ($d['item_id'] ?? ''),
                    $d['item_title'] ?? '',
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Collection', 'Item-Key', 'Item-Titel', 'Library'], $rows);
        }

        $itemCreatorsCreated = $result['item_creators_created_details'] ?? [];
        if ($itemCreatorsCreated !== []) {
            $io->section('Erstellte Item-Creator-Verknüpfungen');
            $rows = [];
            foreach ($itemCreatorsCreated as $d) {
                $creator = trim(($d['creator_firstname'] ?? '') . ' ' . ($d['creator_lastname'] ?? ''));
                $rows[] = [
                    $d['item_key'] ?? (string) ($d['item_id'] ?? ''),
                    $d['item_title'] ?? '',
                    $creator,
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Item-Key', 'Item-Titel', 'Creator', 'Library'], $rows);
        }

        $itemCreatorsDeleted = $result['item_creators_deleted_details'] ?? [];
        if ($itemCreatorsDeleted !== []) {
            $io->section('Entfernte Item-Creator-Verknüpfungen');
            $rows = [];
            foreach ($itemCreatorsDeleted as $d) {
                $creator = trim(($d['creator_firstname'] ?? '') . ' ' . ($d['creator_lastname'] ?? ''));
                $rows[] = [
                    $d['item_key'] ?? (string) ($d['item_id'] ?? ''),
                    $d['item_title'] ?? '',
                    $creator,
                    $d['library'] ?? '',
                ];
            }
            $io->table(['Item-Key', 'Item-Titel', 'Creator', 'Library'], $rows);
        }
    }

    /**
     * Übersprungene Items und Collections in eine JSON-Datei schreiben (für spätere Auswertung).
     */
    private function writeSkippedLog(string $path, array $skippedItems, array $skippedCollections = []): void
    {
        $payload = [
            'synced_at' => date(\DateTimeInterface::ATOM),
            'count' => \count($skippedItems) + \count($skippedCollections),
            'skipped_items' => $skippedItems,
            'skipped_items_count' => \count($skippedItems),
            'skipped_collections' => $skippedCollections,
            'skipped_collections_count' => \count($skippedCollections),
        ];
        $json = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $json);
    }
}
