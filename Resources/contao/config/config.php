<?php

declare(strict_types=1);

/*
 * Backend-Menü: Sektion „Literaturverwaltung“ unter „Inhalte“ (content).
 * Enthält die Tabellen Libraries, Collections, Items (Collections/Items in Phase 1.3).
 * Die Anzeige als letzter Menüpunkt wird in BackendMenuBibliographyPositionListener
 * per Event contao.backend_menu_build gesteuert.
 */
$GLOBALS['BE_MOD']['content']['bibliography'] = [
    'tables' => ['tl_zotero_library'],
];
