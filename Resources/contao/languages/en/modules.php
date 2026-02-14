<?php

declare(strict_types=1);

/*
 * Backend module "Literature management" – English labels.
 * Stored in Resources/contao/languages/en/ for Contao to load with the bundle.
 */
$GLOBALS['TL_LANG']['MOD']['bibliography'] = ['Libraries', 'Manage Zotero libraries, collections and items'];
$GLOBALS['TL_LANG']['MOD']['bibliography_group'] = ['Literature management', 'Zotero libraries, authors and schema data'];
$GLOBALS['TL_LANG']['MOD']['tl_zotero_creator_map'] = ['Author assignment', 'Assign Zotero creator to Contao member'];
$GLOBALS['TL_LANG']['MOD']['tl_zotero_locales'] = ['Locales', 'Localized Zotero schema data (item types, item fields), populated via contao:zotero:fetch-locales or during sync'];

// Category label for Zotero frontend modules (module type dropdown)
$GLOBALS['TL_LANG']['FMD']['zotero'] = 'Zotero';
