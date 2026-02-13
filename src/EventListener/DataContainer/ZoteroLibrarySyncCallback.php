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
 * Wird über die DCA-Operation "sync" (href=key=zotero_sync) aufgerufen
 * und startet den ZoteroSyncService für die ausgewählte Bibliothek.
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
            Message::addInfo($GLOBALS['TL_LANG']['tl_zotero_library']['sync_all_timeout_hint'] ?? 'Sync kann bei vielen Bibliotheken lange dauern. Bei Timeout: Sync einzeln pro Library oder per Kommandozeile (php bin/console contao:zotero:sync).');
            $this->runSyncAllAndRedirect(false);
            return;
        }
        if ($key === 'zotero_reset_sync_all') {
            Message::addInfo($GLOBALS['TL_LANG']['tl_zotero_library']['sync_all_timeout_hint'] ?? 'Sync kann bei vielen Bibliotheken lange dauern. Bei Timeout: Sync einzeln pro Library oder per Kommandozeile (php bin/console contao:zotero:sync).');
            $this->runSyncAllAndRedirect(true);
            return;
        }

        // Einzelne Library-Sync-Operationen
        if ($id <= 0 && \in_array($key, ['zotero_sync', 'zotero_reset_sync'], true)) {
            Message::addError($GLOBALS['TL_LANG']['tl_zotero_library']['sync_error_invalid_id'] ?? 'Zotero-Sync: Ungültige oder fehlende Library-ID.');
            Backend::redirect(Backend::addToUrl('', true, ['key']));
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

    private function runSyncAndRedirect(int $id, bool $resetFirst): void
    {
        set_time_limit(0);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        if ($resetFirst) {
            $this->syncService->resetSyncState($id);
        }

        $this->localeService->fetchAndStore();

        $lang = $GLOBALS['TL_LANG']['tl_zotero_library'] ?? [];
        try {
            $result = $this->syncService->sync($id);
            $libraryTitle = $this->getLibraryTitle($id);
            $done = sprintf($lang['sync_status_done_with_title'] ?? 'Sync Zotero-Library %s abgeschlossen', $libraryTitle);
            $rest = [
                sprintf($lang['sync_status_items'] ?? 'Items: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['items_created'], $result['items_updated'], $result['items_deleted'], $result['items_skipped']),
                sprintf($lang['sync_status_item_creators'] ?? 'Item-Creator-Verknüpfungen: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['item_creators_created'] ?? 0, $result['item_creators_updated'] ?? 0, $result['item_creators_deleted'] ?? 0, $result['item_creators_skipped'] ?? 0),
                sprintf($lang['sync_status_attachments'] ?? 'Attachments: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['attachments_created'] ?? 0, $result['attachments_updated'] ?? 0, $result['attachments_deleted'] ?? 0, $result['attachments_skipped'] ?? 0),
                sprintf($lang['sync_status_collections'] ?? 'Collections: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['collections_created'], $result['collections_updated'], $result['collections_deleted'] ?? 0, $result['collections_skipped'] ?? 0),
                sprintf($lang['sync_status_collection_items'] ?? 'Collection-Item-Verknüpfungen: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['collection_items_created'] ?? 0, $result['collection_items_updated'] ?? 0, $result['collection_items_deleted'] ?? 0, $result['collection_items_skipped'] ?? 0),
            ];
            Message::addConfirmation($done . ' - ' . implode(' | ', $rest));
            if (!empty($result['errors'])) {
                Message::addError(($lang['sync_error_title'] ?? 'Zotero-Sync – Fehler') . ': ' . implode(' | ', $result['errors']));
            }
        } catch (\Throwable $e) {
            $this->addSyncErrorMessage($e, $lang, false);
        }

        $redirectUrl = $resetFirst
            ? Backend::addToUrl('act=edit&id=' . $id, true, ['key'])
            : Backend::addToUrl('', true, ['key']);
        Backend::redirect($redirectUrl);
    }

    private function runSyncAllAndRedirect(bool $resetFirst): void
    {
        set_time_limit(0);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $lang = $GLOBALS['TL_LANG']['tl_zotero_library'] ?? [];
        $libraries = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM tl_zotero_library WHERE published = ?',
            ['1']
        );

        if ($resetFirst) {
            foreach ($libraries as $library) {
                $this->syncService->resetSyncState((int) $library['id']);
            }
        }

        $this->localeService->fetchAndStore();

        foreach ($libraries as $library) {
            $id = (int) $library['id'];
            $title = (string) $library['title'];
            try {
                $result = $this->syncService->sync($id);
                $done = sprintf($lang['sync_status_done_with_title'] ?? 'Sync Zotero-Library %s abgeschlossen', $title);
                $rest = [
                    sprintf($lang['sync_status_items'] ?? 'Items: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['items_created'], $result['items_updated'], $result['items_deleted'], $result['items_skipped']),
                    sprintf($lang['sync_status_item_creators'] ?? 'Item-Creator-Verknüpfungen: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['item_creators_created'] ?? 0, $result['item_creators_updated'] ?? 0, $result['item_creators_deleted'] ?? 0, $result['item_creators_skipped'] ?? 0),
                    sprintf($lang['sync_status_attachments'] ?? 'Attachments: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['attachments_created'] ?? 0, $result['attachments_updated'] ?? 0, $result['attachments_deleted'] ?? 0, $result['attachments_skipped'] ?? 0),
                    sprintf($lang['sync_status_collections'] ?? 'Collections: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['collections_created'], $result['collections_updated'], $result['collections_deleted'] ?? 0, $result['collections_skipped'] ?? 0),
                    sprintf($lang['sync_status_collection_items'] ?? 'Collection-Item-Verknüpfungen: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['collection_items_created'] ?? 0, $result['collection_items_updated'] ?? 0, $result['collection_items_deleted'] ?? 0, $result['collection_items_skipped'] ?? 0),
                ];
                Message::addConfirmation($done . ' - ' . implode(' | ', $rest));
                if (!empty($result['errors'])) {
                    Message::addError(($lang['sync_error_title'] ?? 'Zotero-Sync – Fehler') . ' (' . $title . '): ' . implode(' | ', $result['errors']));
                }
            } catch (\Throwable $e) {
                $this->addSyncErrorMessage($e, $lang, true, $title);
            }
        }

        Backend::redirect(Backend::addToUrl('', true, ['key']));
    }

    private function getLibraryTitle(int $id): string
    {
        $title = $this->connection->fetchOne('SELECT title FROM tl_zotero_library WHERE id = ?', [$id]);
        return $title !== false ? (string) $title : 'ID ' . $id;
    }

    /**
     * Zeigt eine verständliche Fehlermeldung inkl. Hinweis auf CLI/Cron bei Timeout oder Serverfehler.
     */
    private function addSyncErrorMessage(\Throwable $e, array $lang, bool $withLibraryTitle = false, string $libraryTitle = ''): void
    {
        $msg = $e->getMessage();
        $isTimeoutOrServer = str_contains($msg, 'timeout') || str_contains($msg, 'timed out')
            || str_contains($msg, '500') || str_contains($msg, '503')
            || str_contains($msg, 'Maximum execution time') || str_contains($msg, 'Internal Server Error');

        $prefix = $withLibraryTitle && $libraryTitle !== ''
            ? sprintf($lang['sync_error_failed'] ?? 'Zotero-Sync fehlgeschlagen (%s)', $libraryTitle)
            : ($lang['sync_error_failed'] ?? 'Zotero-Sync fehlgeschlagen');
        Message::addError($prefix . ': ' . $msg);

        if ($isTimeoutOrServer) {
            $hint = $lang['sync_error_timeout_hint'] ?? 'Bei großen Bibliotheken: Sync einzeln pro Library ausführen oder über die Kommandozeile (php bin/console contao:zotero:sync). Optional kann der Sync per Cronjob geplant werden – der Cronjob kann keine Meldung im Backend anzeigen, aber z. B. in eine Log-Datei schreiben.';
            Message::addInfo($hint);
        }
    }
}
