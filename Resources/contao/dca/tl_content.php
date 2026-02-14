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

$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_item';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_items';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_member_publications';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_collection_publications';
if (!isset($GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'])) {
    $GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'] = [];
}
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_item'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_item'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_items'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_items'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_member_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_collection_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'];

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
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
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
