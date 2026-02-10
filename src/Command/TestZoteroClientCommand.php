<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

use Raum51\ContaoZoteroBundle\Service\ZoteroClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Test-Command für den ZoteroClient (Phase 2.1).
 *
 * Liegt in src/Command/, weil Contao/Symfony Commands dort erwartet und
 * über Autoconfigure automatisch registriert werden.
 *
 * - Ohne Optionen: Key-Validierung (GET /keys/{key}), User-ID und Rechte.
 * - Mit --list-groups: Gruppen des Keys abrufen (GET /users/{userID}/groups);
 *   die ausgegebene Group-ID kann in tl_zotero_library (library_id, library_type=group) übernommen werden.
 */
#[AsCommand(
    name: 'contao:zotero:test-client',
    description: 'Testet den ZoteroClient (Key-Validierung, optional Gruppenliste für library_id).',
)]
final class TestZoteroClientCommand extends Command
{
    public function __construct(
        private readonly ZoteroClient $zoteroClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('api-key', 'k', InputOption::VALUE_REQUIRED, 'Zotero API-Key (z.B. aus Zotero Account → Settings → Keys)')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'API-Pfad (z.B. /users/12345/items). Ohne Angabe: Key-Validierung /keys/{key}')
            ->addOption('list-groups', null, InputOption::VALUE_NONE, 'Gruppen des Keys auflisten (Group-ID für tl_zotero_library bei library_type=group)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiKey = $input->getOption('api-key');

        if ($apiKey === null || $apiKey === '') {
            $io->error('Bitte --api-key=DEIN_KEY angeben. API-Keys: https://www.zotero.org/settings/keys');

            return self::FAILURE;
        }

        $listGroups = (bool) $input->getOption('list-groups');
        $path = $input->getOption('path');

        if ($listGroups) {
            return $this->executeListGroups($io, $apiKey);
        }

        if ($path === null || $path === '') {
            $path = '/keys/' . $apiKey;
        } else {
            $path = '/' . ltrim($path, '/');
        }

        $io->info('Request: GET ' . $path);

        try {
            $response = $this->zoteroClient->get($path, $apiKey);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);

            $io->success('Status: ' . $status);
            $io->section('Response-Body (gekürzt)');
            $io->text(strlen($content) > 800 ? substr($content, 0, 800) . '…' : $content);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Key validieren, User-ID ermitteln, GET /users/{userID}/groups aufrufen und Gruppen (Group-ID, Name) ausgeben.
     */
    private function executeListGroups(SymfonyStyle $io, string $apiKey): int
    {
        $io->info('Key validieren, anschließend Gruppen abrufen …');

        try {
            $keyResponse = $this->zoteroClient->get('/keys/' . $apiKey, $apiKey);
            $keyResponse->getStatusCode(); // Trigger exception on error
            $keyBody = $keyResponse->getContent(false);
            $keyData = json_decode($keyBody, true, 512, \JSON_THROW_ON_ERROR);

            $userId = $keyData['userID'] ?? $keyData['user'] ?? $keyData['id'] ?? null;
            if ($userId === null || $userId === '') {
                $io->error('User-ID in Key-Antwort nicht gefunden. Erwartet: userID, user oder id.');

                return self::FAILURE;
            }

            $io->success('User-ID: ' . $userId);
            $io->info('Request: GET /users/' . $userId . '/groups');

            $groupsResponse = $this->zoteroClient->get('/users/' . $userId . '/groups', $apiKey);
            $groupsResponse->getStatusCode();
            $groupsBody = $groupsResponse->getContent(false);
            $groups = json_decode($groupsBody, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($groups) || $groups === []) {
                $io->note('Keine Gruppen gefunden (oder Key hat keinen Zugriff auf Gruppen).');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($groups as $group) {
                $id = $group['id'] ?? $group['data']['id'] ?? '-';
                $name = $group['data']['name'] ?? $group['name'] ?? '';
                $rows[] = [(string) $id, $name];
            }
            $io->table(['Group-ID (library_id)', 'Name'], $rows);
            $io->note('Diese Group-ID in tl_zotero_library eintragen (library_type=group), um eine Gruppen-Bibliothek zu syncen.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
