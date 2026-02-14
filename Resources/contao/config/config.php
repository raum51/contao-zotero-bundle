<?php

declare(strict_types=1);

/*
 * Backend-Menü: Sektion „Literaturverwaltung“ unter „Inhalte“ (content).
 * Enthält die Tabellen Libraries, Collections, Items (Collections/Items in Phase 1.3).
 * Die Anzeige als letzter Menüpunkt wird in BackendMenuBibliographyPositionListener
 * per Event contao.backend_menu_build gesteuert.
 */
$GLOBALS['BE_MOD']['content']['bibliography'] = [
    'tables' => ['tl_zotero_library', 'tl_zotero_collection', 'tl_zotero_item'],
];
$GLOBALS['BE_MOD']['content']['tl_zotero_creator_map'] = [
    'tables' => ['tl_zotero_creator_map'],
];
$GLOBALS['BE_MOD']['content']['tl_zotero_locales'] = [
    'tables' => ['tl_zotero_locales'],
];

/*
 * Frontend-Modul-Kategorie „Zotero“ für Zotero-Liste, -Reader und -Suche.
 * Fragment-Controller nutzen category: 'zotero' im AsFrontendModule-Attribut.
 */
if (!isset($GLOBALS['FE_MOD']['zotero'])) {
    $GLOBALS['FE_MOD']['zotero'] = [];
}

$GLOBALS['TL_MODELS']['tl_zotero_item'] = \Raum51\ContaoZoteroBundle\Model\ZoteroItemModel::class;
