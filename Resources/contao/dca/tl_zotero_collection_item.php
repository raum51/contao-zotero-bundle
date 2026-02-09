<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_collection_item – M:N Item ↔ Collection (Pivot).
 * Kein Menüeintrag; nur als Kind von tl_zotero_collection sichtbar.
 */

$GLOBALS['TL_DCA']['tl_zotero_collection_item'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_collection',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'collection_id' => 'index',
                'item_id' => 'index',
                'collection_item' => 'unique collection_id,item_id',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['item_id'],
            'headerFields' => ['title'],
            'panelLayout' => 'limit',
            'disableGrouping' => true,
        ],
        'label' => [
            'fields' => ['item_id'],
            'format' => '%s',
        ],
        'operations' => [
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{item_legend},collection_id,item_id',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_zotero_collection.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'collection_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection_item']['collection_id'],
            'foreignKey' => 'tl_zotero_collection.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'item_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection_item']['item_id'],
            'foreignKey' => 'tl_zotero_item.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
    ],
];
