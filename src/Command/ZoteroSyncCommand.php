<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

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
            'Übersprungene Items in diese Datei schreiben (JSON, z. B. var/log/zotero_skipped.json)'
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

        $result = $this->syncService->sync($id > 0 ? $id : null);

        $io->success('Sync beendet.');
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
        $io->table(['Metrik', 'Anzahl'], $rows);

        $skippedItems = $result['skipped_items'] ?? [];
        $logSkippedPath = $input->getOption('log-skipped');
        if ($logSkippedPath !== null && $logSkippedPath !== '') {
            $this->writeSkippedLog($logSkippedPath, $skippedItems);
            $io->writeln(sprintf('Skipped-Log in <info>%s</info> geschrieben (%d Einträge).', $logSkippedPath, \count($skippedItems)));
        }
        if ($skippedItems !== []) {
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

        return self::SUCCESS;
    }

    /**
     * Übersprungene Items in eine JSON-Datei schreiben (für spätere Auswertung).
     */
    private function writeSkippedLog(string $path, array $skippedItems): void
    {
        $payload = [
            'synced_at' => date(\DateTimeInterface::ATOM),
            'count' => \count($skippedItems),
            'skipped_items' => $skippedItems,
        ];
        $json = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $json);
    }
}
