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
            ['Collections erstellt', $result['collections_created']],
            ['Collections aktualisiert', $result['collections_updated']],
            ['Items erstellt', $result['items_created']],
            ['Items aktualisiert', $result['items_updated']],
            ['Items gelöscht', $result['items_deleted']],
        ];
        if (($result['items_skipped'] ?? 0) > 0) {
            $rows[] = ['Items übersprungen (API-Fehler)', $result['items_skipped']];
        }
        $io->table(['Metrik', 'Anzahl'], $rows);
        if ($result['errors'] !== []) {
            $io->warning('Fehler: ' . implode('; ', $result['errors']));
        }

        return self::SUCCESS;
    }
}
