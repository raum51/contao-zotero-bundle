<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Holt lokalisierte Zotero-Schema-Daten (Item-Typen, Item-Felder) von der API
 * und speichert sie pro Locale in tl_zotero_locales.
 *
 * Kein API-Key nötig. Wird bei jedem Sync mit aufgerufen.
 */
#[AsCommand(
    name: 'contao:zotero:fetch-locales',
    description: 'Lädt lokalisierte Zotero-Schema-Daten (Item-Typen, Item-Felder) und speichert sie in tl_zotero_locales.',
)]
final class FetchLocalesCommand extends Command
{
    public function __construct(
        private readonly ZoteroLocaleService $localeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'show-details',
            null,
            InputOption::VALUE_NONE,
            'Geladene Locales anzeigen'
        );
        $this->addOption(
            'log-changes',
            null,
            InputOption::VALUE_REQUIRED,
            'Änderungen in JSON-Datei schreiben (z. B. var/logs/zotero_locales_changes.json)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Zotero-Locales werden von der API geladen …');

        $result = $this->localeService->fetchAndStore();

        if ($result['errors'] !== []) {
            $io->error('Fehler: ' . implode('; ', $result['errors']));

            return self::FAILURE;
        }

        $io->success(sprintf(
            'Fertig: %d angelegt, %d aktualisiert, %d entfernt.',
            $result['locales_created'],
            $result['locales_updated'],
            $result['locales_deleted']
        ));

        $logPath = $input->getOption('log-changes');
        if ($logPath !== null && $logPath !== '') {
            $this->writeLogFile($logPath, $result);
            $io->note('Änderungen in ' . $logPath . ' gespeichert.');
        }

        if ($input->getOption('show-details')) {
            $locales = $this->localeService->getLocalesToFetch();
            $io->section('Geladene Locales');
            $io->listing($locales);
        }

        return self::SUCCESS;
    }

    private function writeLogFile(string $path, array $result): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'synced_at' => date(\DateTimeInterface::ATOM),
            'command' => 'contao:zotero:fetch-locales',
            'locales_created' => $result['locales_created'],
            'locales_updated' => $result['locales_updated'],
            'locales_deleted' => $result['locales_deleted'],
            'errors' => $result['errors'],
        ];

        file_put_contents($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }
}
