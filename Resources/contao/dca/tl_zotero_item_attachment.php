<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_item_attachment – Zotero-Attachments als Kind von tl_zotero_item.
 * pid = tl_zotero_item.id. Daten von Zotero; Vollständiges JSON in json_data, ausgewählte Felder extrahiert.
 */

$GLOBALS['TL_DCA']['tl_zotero_item_attachment'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_item',
        'doNotCopyRecords' => true,
        'notDeletable' => true,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'zotero_key' => 'index',
                'published' => 'index',
                'trash' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['title'],
            'headerFields' => ['title'],
            'panelLayout' => 'filter;limit',
        ],
        'label' => [
            'fields' => ['title', 'filename'],
            'format' => '%s (%s)',
        ],
        'operations' => [
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{zotero_legend},zotero_key,zotero_version,link_mode,title,filename,content_type,url;{data_legend},charset,md5,json_data;{options_legend},published,trash',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['pid'],
            'foreignKey' => 'tl_zotero_item.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['tstamp'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'sorting' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['sorting'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'zotero_key' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['zotero_key'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 16, 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'zotero_version' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['zotero_version'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'link_mode' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['link_mode'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 32, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 512, 'readonly' => true, 'tl_class' => 'long'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'filename' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['filename'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 512, 'readonly' => true, 'tl_class' => 'long'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'content_type' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['content_type'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'url' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['url'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 2048, 'tl_class' => 'long', 'readonly' => true],
            'sql' => "varchar(2048) NOT NULL default ''",
        ],
        'charset' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['charset'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 32, 'tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'md5' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['md5'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 32, 'tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'json_data' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['json_data'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr long'],
            'sql' => 'mediumtext NULL',
        ],
        'published' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['published'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50', 'toggle' => true, 'disabled' => true],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'trash' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item_attachment']['trash'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50', 'disabled' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
