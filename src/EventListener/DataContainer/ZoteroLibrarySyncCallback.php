<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
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
    ) {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        $key = Input::get('key');
        $id = (int) Input::get('id');

        if ($id <= 0 && \in_array($key, ['zotero_sync', 'zotero_reset_sync'], true)) {
            Message::addError('Zotero-Sync: Ungültige oder fehlende Library-ID.');
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
        if ($resetFirst) {
            $this->syncService->resetSyncState($id);
        }

        try {
            $result = $this->syncService->sync($id);
            $lines = [
                'Zotero-Sync abgeschlossen',
                sprintf('Collections: %d neu, %d aktualisiert', $result['collections_created'], $result['collections_updated']),
                sprintf('Items: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen', $result['items_created'], $result['items_updated'], $result['items_deleted'], $result['items_skipped']),
                sprintf('Collection-Item-Zuordnung: %d neu, %d gelöscht', $result['collection_items_created'] ?? 0, $result['collection_items_deleted'] ?? 0),
                sprintf('Item-Creator-Zuordnung: %d neu, %d gelöscht', $result['item_creators_created'] ?? 0, $result['item_creators_deleted'] ?? 0),
            ];
            Message::addConfirmation(implode("\n", $lines));
            if (!empty($result['errors'])) {
                Message::addError('Zotero-Sync – Fehler: ' . implode(' | ', $result['errors']));
            }
        } catch (\Throwable $e) {
            Message::addError('Zotero-Sync fehlgeschlagen: ' . $e->getMessage());
        }

        $redirectUrl = $resetFirst
            ? Backend::addToUrl('act=edit&id=' . $id, true, ['key'])
            : Backend::addToUrl('', true, ['key']);
        Backend::redirect($redirectUrl);
    }
}

