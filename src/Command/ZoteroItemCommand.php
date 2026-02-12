<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Command;

use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ApiLogCollector;
use Raum51\ContaoZoteroBundle\Service\ZoteroClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ruft das JSON eines Zotero-Items ab (via API).
 *
 * Liegt in src/Command/, weil Contao/Symfony Commands dort erwartet. Der Command
 * durchläuft alle konfigurierten Libraries, bis das Item gefunden wird (bei Angabe
 * des Zotero-Keys), oder nutzt bei tl_zotero_item-ID die bekannte Library direkt.
 *
 * - Argument "item": Zotero-Key (z.B. ABC123) oder tl_zotero_item.id
 * - Option --library: Nur diese Library durchsuchen
 * - Option --find-all: Ohne --library alle Libraries durchsuchen und alle Treffer ausgeben
 *   (Zotero-Keys können library-spezifisch sein – gleicher Key = unterschiedliche Items)
 * - Option --log-api: API-Aufrufe als JSON in Datei schreiben (wie bei contao:zotero:sync)
 */
#[AsCommand(
    name: 'contao:zotero:item',
    description: 'Ruft das JSON eines Zotero-Items über die API ab (Ausgabe oder --log-api).',
)]
final class ZoteroItemCommand extends Command
{
    public function __construct(
        private readonly ZoteroClient $zoteroClient,
        private readonly Connection $connection,
        private readonly ApiLogCollector $apiLogCollector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'item',
                InputArgument::REQUIRED,
                'Zotero-Item-Key (z.B. ABC123) oder tl_zotero_item.id'
            )
            ->addOption(
                'library',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Nur diese Library-ID durchsuchen (ohne Option: alle Libraries)'
            )
            ->addOption(
                'find-all',
                null,
                InputOption::VALUE_NONE,
                'Ohne --library: Alle Libraries durchsuchen und alle Treffer ausgeben (Keys können library-spezifisch sein)'
            )
            ->addOption(
                'log-api',
                null,
                InputOption::VALUE_REQUIRED,
                'API-Aufrufe als JSON in diese Datei schreiben (z.B. var/logs/zotero_item_api.json)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemArg = (string) $input->getArgument('item');
        $libraryIdOpt = $input->getOption('library');
        $findAll = (bool) $input->getOption('find-all');
        $logApiPath = $input->getOption('log-api');

        $libraryId = null;
        if ($libraryIdOpt !== null && $libraryIdOpt !== '') {
            $libraryId = (int) $libraryIdOpt;
            if ($libraryId <= 0) {
                $io->error('Ungültige Library-ID.');

                return self::FAILURE;
            }
        }

        if ($logApiPath !== null && $logApiPath !== '') {
            $this->apiLogCollector->enable($logApiPath, [
                'timestamp' => date(\DateTimeInterface::ATOM),
                'command' => 'contao:zotero:item',
                'options' => [
                    'item' => $itemArg,
                    'library' => $libraryId > 0 ? $libraryId : null,
                    'find_all' => $findAll,
                ],
            ]);
        }

        try {
            $resolved = $this->resolveItem($itemArg, $libraryId);
            if ($resolved === null) {
                $io->error('Item nicht gefunden. Prüfe Zotero-Key oder tl_zotero_item.id.');

                return self::FAILURE;
            }

            $itemKey = $resolved['key'];
            $effectiveLibraryId = $resolved['library_id'] ?? $libraryId;

            // --find-all ohne --library: immer alle Libraries durchsuchen
            if ($findAll && $libraryId === null) {
                $effectiveLibraryId = null;
            }

            $libraries = $this->fetchLibraries($effectiveLibraryId);
            if ($libraries === []) {
                $io->error('Keine Libraries konfiguriert bzw. Library nicht gefunden.');

                return self::FAILURE;
            }

            $result = $this->fetchItemFromLibraries($libraries, $itemKey, $findAll);
            $isEmpty = $findAll ? $result === [] : $result === null;
            if ($isEmpty) {
                $io->error(sprintf('Item mit Key "%s" in keiner der durchsuchten Libraries gefunden.', $itemKey));

                return self::FAILURE;
            }

            $json = json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
            $io->writeln($json);

            if ($logApiPath !== null && $logApiPath !== '') {
                $this->apiLogCollector->flush();
                $io->writeln(sprintf('API-Log in <info>%s</info> geschrieben.', $logApiPath));
            }

            return self::SUCCESS;
        } finally {
            if ($logApiPath !== null && $logApiPath !== '' && $this->apiLogCollector->isEnabled()) {
                $this->apiLogCollector->flush();
            }
        }
    }

    /**
     * Ermittelt Zotero-Key und ggf. Library-ID. Bei numerischem Argument: Lookup in tl_zotero_item.
     *
     * @return array{key: string, library_id: int|null}|null
     */
    private function resolveItem(string $itemArg, ?int $libraryId): ?array
    {
        if (ctype_digit($itemArg)) {
            $id = (int) $itemArg;
            $qb = $this->connection->createQueryBuilder();
            $qb->select('zotero_key', 'pid')->from('tl_zotero_item')->where($qb->expr()->eq('id', ':id'))->setParameter('id', $id);
            if ($libraryId !== null) {
                $qb->andWhere($qb->expr()->eq('pid', ':pid'))->setParameter('pid', $libraryId);
            }
            $stmt = $qb->executeQuery();
            $row = $stmt->fetchAssociative();

            if ($row === false || !isset($row['zotero_key'])) {
                return null;
            }

            return [
                'key' => (string) $row['zotero_key'],
                'library_id' => isset($row['pid']) ? (int) $row['pid'] : null,
            ];
        }

        return ['key' => $itemArg, 'library_id' => null];
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
     * @param list<array<string, mixed>> $libraries
     *
     * @return array<string, mixed>|array<int, array<string, mixed>>|null Bei findAll: Liste aller Treffer (kann leer sein); sonst erstes gefundenes Item oder null
     */
    private function fetchItemFromLibraries(array $libraries, string $itemKey, bool $findAll = false): array|null
    {
        $found = [];

        foreach ($libraries as $library) {
            $prefix = $this->libraryPrefix($library);
            $path = $prefix . '/items/' . $itemKey;
            $apiKey = (string) ($library['api_key'] ?? '');

            if ($apiKey === '') {
                continue;
            }

            try {
                $response = $this->zoteroClient->get($path, $apiKey);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    $content = $response->getContent(false);
                    $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
                    $item = \is_array($decoded) ? $decoded : ['raw' => $content];

                    if ($findAll) {
                        $found[] = $item;
                    } else {
                        return $item;
                    }
                }

                if ($statusCode === 404) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $findAll ? $found : null;
    }
}
