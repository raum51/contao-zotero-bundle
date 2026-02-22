<?php

declare(strict_types=1);

/*
 * Backend-Menü: Eigene Top-Level-Kategorie „Literaturverwaltung“ (BE_MOD['zotero']).
 * Enthält Bibliotheken, Autoren-Zuordnung und Locales.
 * Die Position (nach „Inhalte“) wird in BackendMenuBibliographyPositionListener gesteuert.
 */
$GLOBALS['BE_MOD']['zotero']['bibliography'] = [
    'tables' => ['tl_zotero_library', 'tl_zotero_collection', 'tl_zotero_item'],
];
$GLOBALS['BE_MOD']['zotero']['tl_zotero_creator_map'] = [
    'tables' => ['tl_zotero_creator_map'],
];
$GLOBALS['BE_MOD']['zotero']['tl_zotero_locales'] = [
    'tables' => ['tl_zotero_locales'],
];

/*
 * CE-only: Frontend-Module (Zotero-Liste, -Reader, -Suche) wurden durch Content-Elemente ersetzt.
 * FE_MOD zotero entfernt (16.02.2026).
 */

$GLOBALS['TL_MODELS']['tl_zotero_item'] = \Raum51\ContaoZoteroBundle\Model\ZoteroItemModel::class;
