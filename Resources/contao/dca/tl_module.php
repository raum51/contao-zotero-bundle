<?php

declare(strict_types=1);

/*
 * Erweiterung tl_module – Zotero-Frontend-Modul-Typen.
 * Typen: zotero_list, zotero_reader, zotero_search (Konfiguration in Phase 4).
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_list'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_libraries,zotero_collections,zotero_item_types,zotero_template,zotero_reader_module,zotero_search_module;{config_legend},numberOfItems,perPage,zotero_list_order,zotero_list_sort_direction_date,zotero_list_group;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_reader'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_libraries,zotero_template;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'zotero_search_enabled';
$GLOBALS['TL_DCA']['tl_module']['palettes']['zotero_search'] =
    '{title_legend},name,headline,type;{zotero_legend},zotero_libraries,zotero_list_page,zotero_search_enabled,zotero_search_show_author,zotero_search_show_year,zotero_search_show_item_type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['zotero_search_enabled'] =
    'zotero_search_sort_by_weight,zotero_search_weight_title,zotero_search_weight_creators,zotero_search_weight_tags,zotero_search_weight_publication_title,zotero_search_weight_year,zotero_search_weight_abstract,zotero_search_weight_zotero_key,zotero_search_token_mode,zotero_search_max_tokens';

$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_list';
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_reader';
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['options'][] = 'zotero_search';
if (!isset($GLOBALS['TL_DCA']['tl_module']['fields']['type']['reference'])) {
    $GLOBALS['TL_DCA']['tl_module']['fields']['type']['reference'] = [];
}
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['reference']['zotero_list'] = &$GLOBALS['TL_LANG']['tl_module']['zotero_list'];
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['reference']['zotero_reader'] = &$GLOBALS['TL_LANG']['tl_module']['zotero_reader'];
$GLOBALS['TL_DCA']['tl_module']['fields']['type']['reference']['zotero_search'] = &$GLOBALS['TL_LANG']['tl_module']['zotero_search'];

$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_libraries'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_libraries'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroLibraryOptionsCallback::class, '__invoke'],
    'eval' => ['mandatory' => true, 'multiple' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
    'relation' => ['type' => 'hasMany', 'table' => 'tl_zotero_library', 'field' => 'id', 'load' => 'lazy'],
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_collections'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_collections'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
    'relation' => ['type' => 'hasMany', 'table' => 'tl_zotero_collection', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_item_types'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_item_types'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroItemTypesOptionsCallback::class, '__invoke'],
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_list_page'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_page'],
    'exclude' => true,
    'inputType' => 'pageTree',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_page', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['cite_content', 'json_dl', 'fields'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['zotero_template_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default 'cite_content'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_reader_module'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_reader_module'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroReaderModuleOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_module'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_module'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroSearchModuleOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_enabled'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_enabled'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_sort_by_weight'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_sort_by_weight'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_title'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_title'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '100'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_creators'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_creators'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '10'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_tags'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_tags'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '10'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_publication_title'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_publication_title'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_year'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_year'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_abstract'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_abstract'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_weight_zotero_key'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_zotero_key'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50', 'minval' => 0],
    'sql' => "varchar(8) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_show_author'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_author'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_show_year'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_year'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_show_item_type'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_item_type'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_fields'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_fields'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'tl_class' => 'w50', 'placeholder' => 'title,tags,abstract'],
    'sql' => "varchar(64) NOT NULL default 'title,tags,abstract'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_token_mode'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['and', 'or', 'frontend'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'and'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_max_tokens'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_tokens'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '10'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_search_max_results'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_results'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '0'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_list_order'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_order'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['order_author_date', 'order_year_author', 'order_title'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_order_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default 'order_title'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_list_sort_direction_date'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['asc', 'desc'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(4) NOT NULL default 'desc'",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['zotero_list_group'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_group'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['', 'library', 'collection', 'item_type', 'year'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['zotero_list_group_options'],
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true],
    'sql' => "varchar(32) NOT NULL default ''",
];
