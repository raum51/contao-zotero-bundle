<?php

declare(strict_types=1);

/*
 * Erweiterung tl_content – Zotero-Inhaltselement-Typen.
 * Typen: zotero_item (CE-only), zotero_items, zotero_member_publications, zotero_collection_publications.
 */

$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'zotero_item_mode';
$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_item'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_item_mode,zotero_template;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['zotero_item_mode_fixed'] = 'zotero_item_id';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['zotero_item_mode_from_url'] = 'zotero_libraries';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_items'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_items,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_member_publications'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_member,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_collection_publications'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_collection,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_list'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_libraries,zotero_collections,zotero_item_types,zotero_author,zotero_template,zotero_reader_element,zotero_search_element,zotero_search_module;{config_legend},numberOfItems,perPage,zotero_list_order,zotero_list_sort_direction_date,zotero_list_group;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_search'] =
    '{type_legend},title,type,headline;{zotero_legend},zotero_libraries,zotero_list_page,zotero_search_show_author,zotero_search_show_year,zotero_search_show_item_type;{search_config_legend},zotero_search_fields,zotero_search_token_mode,zotero_search_max_tokens,zotero_search_max_results;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_item';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_items';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_member_publications';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_collection_publications';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_list';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_search';
if (!isset($GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'])) {
    $GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'] = [];
}
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_item'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_item'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_items'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_items'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_member_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_collection_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_list'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_list'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_search'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_search'];

$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_item_mode'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['fixed', 'from_url'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode_options'],
    'eval' => ['mandatory' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'fixed'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_item_id'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_item_id'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroItemOptionsCallback::class, '__invoke'],
    'eval' => ['mandatory' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_zotero_item', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_libraries'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_libraries'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroLibraryOptionsCallback::class, '__invoke'],
    'eval' => ['mandatory' => true, 'multiple' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
    'relation' => ['type' => 'hasMany', 'table' => 'tl_zotero_library', 'field' => 'id', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_items'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_items'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
    'relation' => ['type' => 'hasMany', 'table' => 'tl_zotero_item', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_member'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_member'],
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_member', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_collection'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_collection'],
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_zotero_collection', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['cite_content', 'json_dl', 'fields'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_template_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default 'cite_content'",
];

// Felder für zotero_list (CE)
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_collections'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_collections'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroCollectionsOptionsCallback::class, '__invoke'],
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_item_types'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_item_types'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroItemTypesOptionsCallback::class, '__invoke'],
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_author'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_author'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroAuthorOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_reader_element'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_reader_element'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroReaderElementOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_element'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_element'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroSearchElementOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_module'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_module'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [\Raum51\ContaoZoteroBundle\EventListener\DataContainer\ZoteroSearchModuleOptionsCallback::class, '__invoke'],
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];

// Felder für zotero_search (CE)
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_list_page'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_page'],
    'exclude' => true,
    'inputType' => 'pageTree',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'table' => 'tl_page', 'field' => 'id'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_show_author'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_author'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_show_year'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_year'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_show_item_type'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_item_type'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_fields'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_fields'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'tl_class' => 'w50', 'placeholder' => 'title,tags,abstract'],
    'sql' => "varchar(64) NOT NULL default 'title,tags,abstract'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_token_mode'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['and', 'or'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(4) NOT NULL default 'and'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_max_tokens'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_tokens'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '10'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_search_max_results'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_results'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '0'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['numberOfItems'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['numberOfItems'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '0'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['perPage'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['perPage'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default '0'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_list_order'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_order'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['order_author_date', 'order_year_author', 'order_title'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_order_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default 'order_title'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_list_sort_direction_date'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['asc', 'desc'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(4) NOT NULL default 'desc'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['zotero_list_group'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_group'],
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['', 'library', 'collection', 'item_type', 'year'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['zotero_list_group_options'],
    'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true],
    'sql' => "varchar(32) NOT NULL default ''",
];
