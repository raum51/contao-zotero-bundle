<?php

declare(strict_types=1);

/*
 * Erweiterung tl_module – Zotero-Frontend-Modul-Typen.
 * Typen: zotero_list, zotero_reader, zotero_search (Konfiguration in Phase 4).
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_list'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_library,zotero_collections;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_reader'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_library;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_search'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_library,zotero_list_page;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_list';
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_reader';
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_search';

$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_library'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_library'],
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_zotero_library', 'field' => 'id', 'where' => [['field' => 'published', 'value' => '1']]],
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_collections'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_collections'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
    'relation' => ['type' => 'hasMany', 'table' => 'tl_zotero_collection', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_list_page'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_page'],
    'exclude' => true,
    'inputType' => 'pageTree',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_page', 'field' => 'id'],
];
