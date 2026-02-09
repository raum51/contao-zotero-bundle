<?php

declare(strict_types=1);

/*
 * Erweiterung tl_content – Zotero-Inhaltselement-Typen.
 * Typen: zotero_items, zotero_member_publications, zotero_collection_publications (Phase 4).
 */

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_items'] =
    '{type_legend},type,headline;{zotero_legend},zotero_items,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_member_publications'] =
    '{type_legend},type,headline;{zotero_legend},zotero_member,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['zotero_collection_publications'] =
    '{type_legend},type,headline;{zotero_legend},zotero_collection,zotero_template;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_items';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_member_publications';
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options'][] = 'zotero_collection_publications';
if (!isset($GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'])) {
    $GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference'] = [];
}
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_items'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_items'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_member_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'];
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['reference']['zotero_collection_publications'] = &$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'];

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
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default 'cite_content'",
];
