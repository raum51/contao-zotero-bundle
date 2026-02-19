<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\MessageHandler;

use Contao\CoreBundle\Job\Job;
use Contao\CoreBundle\Job\Jobs;
use Psr\Log\LoggerInterface;
use Raum51\ContaoZoteroBundle\Message\ZoteroSyncMessage;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Verarbeitet ZoteroSyncMessage asynchron.
 *
 * Liegt in src/MessageHandler/, da Message-Handler als Teil des Messenger-Patterns
 * die konkrete Verarbeitung durchführen.
 *
 * Contao 5.6: Job-Framework (markPending, markCompleted, markFailed).
 * Contao 5.7: withProgressFromAmounts (Fortschrittsbalken), addAttachment (Sync-Report).
 */
#[AsMessageHandler]
final class ZoteroSyncMessageHandler
{
    public function __construct(
        private readonly ZoteroSyncService $syncService,
        private readonly ZoteroLocaleService $localeService,
        private readonly LoggerInterface $logger,
        /** Null wenn Contao < 5.6 (kein Job-Framework) */
        private readonly ?Jobs $jobs = null,
    ) {
    }

    public function __invoke(ZoteroSyncMessage $message): void
    {
        $libraryId = $message->libraryId;
        $jobUuid = $message->jobUuid;

        $job = null;
        if ($jobUuid !== null && $this->jobs !== null) {
            try {
                $job = $this->jobs->getByUuid($jobUuid);
            } catch (\Throwable $e) {
                $this->logger->error('Zotero Sync Job: Job mit UUID {uuid} nicht gefunden', ['uuid' => $jobUuid, 'error' => $e->getMessage()]);
            }
            if ($job !== null && method_exists($job, 'isCompleted') && $job->isCompleted()) {
                return;
            }
            if ($job !== null) {
                $job = $job->markPending();
                $this->jobs->persist($job);
            }
        }

        $this->logger->info('Zotero Sync (Messenger): Start {scope}', [
            'scope' => $libraryId !== null ? 'Library ' . $libraryId : 'alle Libraries',
            'resetFirst' => $message->resetFirst,
        ]);

        try {
            $this->localeService->fetchAndStore();
        } catch (\Throwable $e) {
            $this->handleFailure($job, 'Locales laden fehlgeschlagen: ' . $e->getMessage());
            return;
        }

        if ($message->resetFirst) {
            if ($libraryId !== null && $libraryId > 0) {
                $this->syncService->resetSyncState($libraryId);
            } else {
                $this->syncService->resetAllSyncStates();
            }
        }

        $progressCallback = $this->createProgressCallback($job);

        try {
            $result = $this->syncService->sync($libraryId, true, null, [], $progressCallback);
        } catch (\Throwable $e) {
            $this->handleFailure($job, 'Sync fehlgeschlagen: ' . $e->getMessage());
            return;
        }

        $errors = $result['errors'] ?? [];
        if ($errors !== []) {
            $msg = implode(' ', $errors);
            $this->handleFailure($job, $msg);
            return;
        }

        if ($job !== null && $this->jobs !== null) {
            $this->addSuccessAttachment($job, $result);
            $job = $job->markCompleted();
            $this->jobs->persist($job);
        }

        $this->logger->info('Zotero Sync (Messenger): Abgeschlossen {scope}', [
            'scope' => $libraryId !== null ? 'Library ' . $libraryId : 'alle Libraries',
        ]);
    }

    private function handleFailure(?Job $job, string $message): void
    {
        $this->logger->error('Zotero Sync (Messenger): {message}', ['message' => $message]);

        if ($job !== null && $this->jobs !== null) {
            $this->addFailureAttachment($job, $message);
            $job = $job->markFailed([$message]);
            $this->jobs->persist($job);
        }
    }

    /**
     * Progress-Callback für withProgressFromAmounts (Contao 5.7).
     * In 5.6: null (kein Fortschrittsbalken).
     */
    private function createProgressCallback(?Job $job): ?callable
    {
        if ($job === null || $this->jobs === null || !method_exists(Job::class, 'withProgressFromAmounts')) {
            return null;
        }

        return function (int $done, ?int $total) use (&$job): void {
            $job = $job->withProgressFromAmounts($done, $total);
            $this->jobs->persist($job);
        };
    }

    private function addSuccessAttachment(Job $job, array $result): void
    {
        if ($this->jobs === null || !method_exists(Jobs::class, 'addAttachment')) {
            return;
        }

        $lines = [
            'Zotero Sync Report',
            '==================',
            '',
            'Gesamt:',
            '  Collections: ' . ($result['collections_created'] ?? 0) . ' created, ' . ($result['collections_updated'] ?? 0) . ' updated, ' . ($result['collections_deleted'] ?? 0) . ' deleted',
            '  Items: ' . ($result['items_created'] ?? 0) . ' created, ' . ($result['items_updated'] ?? 0) . ' updated, ' . ($result['items_deleted'] ?? 0) . ' deleted',
            '  Attachments: ' . ($result['attachments_created'] ?? 0) . ' created, ' . ($result['attachments_updated'] ?? 0) . ' updated, ' . ($result['attachments_deleted'] ?? 0) . ' deleted',
        ];

        $libraryStats = $result['library_stats'] ?? [];
        if ($libraryStats !== []) {
            $lines[] = '';
            $lines[] = 'Je Library:';
            foreach ($libraryStats as $stats) {
                $title = $stats['title'] ?? 'Unbekannt';
                $lines[] = '';
                $lines[] = '  ' . $title;
                $lines[] = '    Collections: ' . ($stats['collections_created'] ?? 0) . ' created, ' . ($stats['collections_updated'] ?? 0) . ' updated, ' . ($stats['collections_deleted'] ?? 0) . ' deleted';
                $lines[] = '    Items: ' . ($stats['items_created'] ?? 0) . ' created, ' . ($stats['items_updated'] ?? 0) . ' updated, ' . ($stats['items_deleted'] ?? 0) . ' deleted';
                $lines[] = '    Attachments: ' . ($stats['attachments_created'] ?? 0) . ' created, ' . ($stats['attachments_updated'] ?? 0) . ' updated, ' . ($stats['attachments_deleted'] ?? 0) . ' deleted';
            }
        }

        $this->jobs->addAttachment($job, 'zotero_sync_report.txt', implode("\n", $lines));
    }

    private function addFailureAttachment(Job $job, string $errorMessage): void
    {
        if ($this->jobs === null || !method_exists(Jobs::class, 'addAttachment')) {
            return;
        }

        $this->jobs->addAttachment($job, 'zotero_sync_error.txt', "Zotero Sync fehlgeschlagen\n\n" . $errorMessage);
    }
}
