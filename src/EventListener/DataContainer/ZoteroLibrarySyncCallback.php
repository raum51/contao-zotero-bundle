<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSyncService;

/**
 * Backend-Sync-Trigger für tl_zotero_library.
 *
 * Wird über die DCA-Operation "sync" (href=key=zotero_sync) aufgerufen.
 * Führt den Sync synchron im Request aus. Bei großen Bibliotheken kann es
 * zu Timeouts kommen – dann Sync über CLI: php bin/console contao:zotero:sync
 *
 * Liegt unter EventListener/DataContainer/, weil es ein DCA-Callback
 * (config.onload) für tl_zotero_library ist.
 */
#[AsCallback(table: 'tl_zotero_library', target: 'config.onload')]
final class ZoteroLibrarySyncCallback
{
    public function __construct(
        private readonly ZoteroSyncService $syncService,
        private readonly ZoteroLocaleService $localeService,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        $key = Input::get('key');
        $id = (int) Input::get('id');

        // Globale Sync-Operationen (alle publizierten Libraries)
        if ($key === 'zotero_sync_all') {
            $this->runSyncAndRedirect(null, false);
            return;
        }
        if ($key === 'zotero_reset_sync_all') {
            $this->runSyncAndRedirect(null, true);
            return;
        }

        // Einzelne Library-Sync-Operationen
        if ($id <= 0 && \in_array($key, ['zotero_sync', 'zotero_reset_sync'], true)) {
            Message::addError($GLOBALS['TL_LANG']['tl_zotero_library']['sync_error_invalid_id'] ?? 'Zotero-Sync: Ungültige oder fehlende Library-ID.');
            Backend::redirect(Backend::addToUrl('', true, ['key']));
            return;
        }

        if ($key === 'zotero_reset_sync') {
            $this->runSyncAndRedirect($id, true);
            return;
        }

        if ($key !== 'zotero_sync') {
            return;
        }

        $this->runSyncAndRedirect($id, false);
    }

    /**
     * Führt den Sync synchron im Request aus und leitet danach weiter.
     */
    private function runSyncAndRedirect(?int $libraryId, bool $resetFirst): void
    {
        $lang = $GLOBALS['TL_LANG']['tl_zotero_library'] ?? [];

        try {
            $this->localeService->fetchAndStore();
        } catch (\Throwable $e) {
            Message::addError(($lang['sync_error_failed'] ?? 'Zotero-Sync fehlgeschlagen') . ': ' . $e->getMessage());
            $this->redirectAfterSync($libraryId, $resetFirst);
            return;
        }

        if ($resetFirst) {
            if ($libraryId !== null && $libraryId > 0) {
                $this->syncService->resetSyncState($libraryId);
            } else {
                $this->syncService->resetAllSyncStates();
            }
        }

        try {
            $result = $this->syncService->sync($libraryId, true, null, []);
        } catch (\Throwable $e) {
            $hint = $lang['sync_error_timeout_hint'] ?? '';
            $msg = ($lang['sync_error_failed'] ?? 'Zotero-Sync fehlgeschlagen') . ': ' . $e->getMessage();
            if ($hint !== '') {
                $msg .= ' ' . $hint;
            }
            Message::addError($msg);
            $this->redirectAfterSync($libraryId, $resetFirst);
            return;
        }

        $errors = $result['errors'] ?? [];
        if ($errors !== []) {
            $msg = implode(' ', $errors);
            $hint = $lang['sync_error_timeout_hint'] ?? '';
            if ($hint !== '') {
                $msg .= ' ' . $hint;
            }
            Message::addError($msg);
            $this->redirectAfterSync($libraryId, $resetFirst);
            return;
        }

        if ($libraryId !== null && $libraryId > 0) {
            $title = $this->getLibraryTitle($libraryId);
            Message::addConfirmation($title !== null
                ? sprintf($lang['sync_status_done_with_title'] ?? 'Sync Zotero-Library %s abgeschlossen', $title)
                : ($lang['sync_status_done'] ?? 'Zotero-Sync abgeschlossen'));
        } else {
            Message::addConfirmation($lang['sync_status_done'] ?? 'Zotero-Sync abgeschlossen');
        }

        $this->redirectAfterSync($libraryId, $resetFirst);
    }

    private function redirectAfterSync(?int $libraryId, bool $resetFirst): void
    {
        $redirectUrl = $libraryId !== null && $libraryId > 0 && $resetFirst
            ? Backend::addToUrl('act=edit&id=' . $libraryId, true, ['key'])
            : Backend::addToUrl('', true, ['key']);
        Backend::redirect($redirectUrl);
    }

    private function getLibraryTitle(int $libraryId): ?string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT title FROM tl_zotero_library WHERE id = ?',
            [$libraryId]
        );

        return $row !== false ? (string) $row['title'] : null;
    }
}
