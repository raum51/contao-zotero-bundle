<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_item_creator – M:N Item ↔ Creator-Map (Pivot).
 * Kein Menüeintrag; nur als Kind von tl_zotero_item sichtbar (Creator↔Member zuordnen).
 */

$GLOBALS['TL_DCA']['tl_zotero_item_creator'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_item',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'item_id' => 'index',
                'creator_map_id' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['creator_map_id'],
            'headerFields' => ['title'],
            'panelLayout' => 'limit',
            'disableGrouping' => true,
        ],
        'label' => [
            'fields' => ['creator_map_id'],
            'format' => '%s',
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{creator_legend},item_id,creator_map_id',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_zotero_item.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'item_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_creator']['item_id'],
            'foreignKey' => 'tl_zotero_item.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'creator_map_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_creator']['creator_map_id'],
            'exclude' => true,
            'inputType' => 'select',
            'eval' => [
                'mandatory' => true,
                'chosen' => true,
                'tl_class' => 'w50',
            ],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'table' => 'tl_zotero_creator_map', 'field' => 'id'],
        ],
    ],
];
