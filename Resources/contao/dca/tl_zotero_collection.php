<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_collection – Zotero-Collections pro Library (hierarchisch).
 * Daten von Zotero: nur show + toggle (published). ctable => tl_zotero_collection_item.
 */

$GLOBALS['TL_DCA']['tl_zotero_collection'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_library',
        'ctable' => ['tl_zotero_collection_item'],
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'parent_id' => 'index',
                'zotero_key' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['sorting'],
            'headerFields' => ['title'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()"',
            ],
        ],
        'operations' => [
            'toggle' => [
                'href' => 'act=toggle&amp;field=published',
                'icon' => 'visible.svg',
                'primary' => true,
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,zotero_key;{structure_legend},parent_id,sorting;{publish_legend},published',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['pid'],
            'foreignKey' => 'tl_zotero_library.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['tstamp'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'parent_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['parent_id'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NULL DEFAULT NULL',
        ],
        'sorting' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['sorting'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'zotero_key' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['zotero_key'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 16, 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'published' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_collection']['published'],
            'exclude' => true,
            'filter' => true,
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
    ],
];
