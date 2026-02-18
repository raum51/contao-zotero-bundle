<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Cron;

use Contao\CoreBundle\Cron\Cron;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Exception\CronExecutionSkippedException;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSyncService;

/**
 * Cronjob für automatischen Zotero-Sync.
 *
 * Läuft stündlich (hourly), nur im CLI-Scope. Prüft pro Library ob sync_interval
 * (in Stunden) abgelaufen ist und synchronisiert fällige published Libraries.
 *
 * Liegt in src/Cron/ – Standard-Ort für Cronjob-Services im Bundle.
 */
#[AsCronJob('hourly')]
final class ZoteroSyncCron
{
    public function __construct(
        private readonly ZoteroSyncService $syncService,
        private readonly ZoteroLocaleService $localeService,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(string $scope): void
    {
        if (Cron::SCOPE_WEB === $scope) {
            throw new CronExecutionSkippedException();
        }

        $libraries = $this->fetchDueLibraries();
        if ($libraries === []) {
            return;
        }

        $this->logger->info('ZoteroSyncCron: {count} Library/Libraries fällig', ['count' => \count($libraries)]);

        $this->localeService->fetchAndStore();

        foreach ($libraries as $library) {
            $id = (int) $library['id'];
            $title = (string) ($library['title'] ?? 'ID ' . $id);
            try {
                $this->syncService->sync($id, true);
                $this->logger->info('ZoteroSyncCron: Library "{title}" (ID {id}) synchronisiert', ['title' => $title, 'id' => $id]);
            } catch (\Throwable $e) {
                $this->logger->error('ZoteroSyncCron: Sync fehlgeschlagen für Library "{title}" (ID {id}): {message}', [
                    'title' => $title,
                    'id' => $id,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Lädt published Libraries mit sync_interval > 0, bei denen das Intervall abgelaufen ist.
     * sync_interval ist in Stunden; last_sync_at + (sync_interval * 3600) <= now.
     * last_sync_at = 0 gilt als sofort fällig.
     *
     * @return list<array{id: int|string, title: string}>
     */
    private function fetchDueLibraries(): array
    {
        $now = time();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, sync_interval, last_sync_at FROM tl_zotero_library WHERE published = ? AND sync_interval > ? ORDER BY id',
            ['1', 0]
        );

        $due = [];
        foreach ($rows as $row) {
            $intervalHours = (int) ($row['sync_interval'] ?? 0);
            if ($intervalHours <= 0) {
                continue;
            }
            $lastSyncAt = (int) ($row['last_sync_at'] ?? 0);
            $threshold = $lastSyncAt + ($intervalHours * 3600);
            if ($lastSyncAt === 0 || $threshold <= $now) {
                $due[] = $row;
            }
        }

        return $due;
    }
}
