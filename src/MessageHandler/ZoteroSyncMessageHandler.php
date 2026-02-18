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
 * Ab Contao 5.6: Integration mit Job-Framework (markPending, markCompleted, markFailed).
 * Bei Contao 5.3/5.4/5.5: Jobs-Service fehlt – Sync läuft trotzdem, nur ohne Job-Status.
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
            if ($job !== null && $job->isCompleted()) {
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

        try {
            $result = $this->syncService->sync($libraryId, true, null, []);
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
            $job = $job->markFailed([$message]);
            $this->jobs->persist($job);
        }
    }
}
