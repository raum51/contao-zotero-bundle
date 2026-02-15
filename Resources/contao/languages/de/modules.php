<?php

declare(strict_types=1);

/*
 * Backend-Modul „Literaturverwaltung“ – deutsche Bezeichnungen.
 * Liegt in Resources/contao/languages/de/, damit Contao sie dem Bundle zuordnet.
 */
$GLOBALS['TL_LANG']['MOD']['bibliography'] = ['Bibliotheken', 'Zotero-Bibliotheken, Collections und Publikationen verwalten'];
$GLOBALS['TL_LANG']['MOD']['bibliography_group'] = ['Literaturverwaltung', 'Zotero-Bibliotheken, Autoren und Schema-Daten'];
$GLOBALS['TL_LANG']['MOD']['tl_zotero_creator_map'] = ['Autoren-Zuordnung', 'Zotero-Creator zu Contao-Mitglied zuordnen'];
$GLOBALS['TL_LANG']['MOD']['tl_zotero_locales'] = ['Locales', 'Lokalisierte Zotero-Schema-Daten (Publikations-Typen, Publikations-Felder), befüllt per contao:zotero:fetch-locales oder bei Sync'];

// Kategorie-Label für Zotero-Frontend-Module (Dropdown im Modul-Typ)
$GLOBALS['TL_LANG']['FMD']['zotero'] = 'Zotero';
