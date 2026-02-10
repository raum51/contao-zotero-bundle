<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Fügt in der Library-Bearbeitungsmaske den Button
 * "Synchronisation zurücksetzen und starten" hinzu (nur diese Library).
 *
 * Liegt unter EventListener/DataContainer/, da es der DCA-Callback edit.buttons ist.
 */
#[AsCallback(table: 'tl_zotero_library', target: 'edit.buttons')]
final class ZoteroLibraryEditButtonsListener
{
    public function __invoke(array $buttons, DataContainer $dc): array
    {
        $id = (int) $dc->id;
        if ($id <= 0) {
            return $buttons;
        }

        $url = Backend::addToUrl('key=zotero_reset_sync&id=' . $id);
        $label = $GLOBALS['TL_LANG']['tl_zotero_library']['reset_sync'][0] ?? 'Synchronisation zurücksetzen und starten';
        $buttons['reset_sync'] = '<a href="' . $url . '" class="tl_submit">' . $label . '</a>';

        return $buttons;
    }
}
