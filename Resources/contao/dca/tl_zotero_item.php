<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_item – Zotero-Publikationen. Daten von Zotero; edit nur für Creator↔Member.
 * Operationen: show, edit, toggle. ctable => tl_zotero_item_creator.
 */

use Contao\Backend;

$GLOBALS['TL_DCA']['tl_zotero_item'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_library',
        'ctable' => ['tl_zotero_item_creator', 'tl_zotero_item_attachment'],
        'doNotCopyRecords' => true,
        'notDeletable' => true,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'zotero_key' => 'index',
                'alias' => 'unique',
                'published' => 'index',
                'trash' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'flag' => 1,
            'fields' => ['title'],
            'headerFields' => ['title'],
            'panelLayout' => 'filter;search,limit',
            'filter' => [['title!=?', '']],
        ],
        'label' => [
            'fields' => ['title', 'year', 'item_type'],
            'format' => '%s (%s) - %s',
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
            'download' => [
                'href' => 'act=toggle&amp;field=download_attachments',
                'icon' => 'bundles/raum51contaozotero/icons/cloud-download.svg',
                'primary' => true,
            ],
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
                'primary' => false,
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
                'primary' => false,
            ],
        ],
    ],
    'palettes' => [
        'default' => '{zotero_legend},zotero_key,alias,zotero_version,title,item_type,year,date,publication_title;{content_legend},cite_content,bib_content,abstract;{data_legend},json_data,tags;{options_legend},download_attachments,published,trash',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['pid'],
            'foreignKey' => 'tl_zotero_library.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['tstamp'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'zotero_key' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['zotero_key'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 16, 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'alias' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['alias'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'alias', 'doNotCopy' => true, 'unique' => true, 'maxlength' => 255, 'readonly' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
        'zotero_version' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['zotero_version'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 512, 'readonly' => true, 'tl_class' => 'long'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'item_type' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['item_type'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 32, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'year' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['year'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'date' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['date'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'date', 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'publication_title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['publication_title'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 512, 'tl_class' => 'long'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'cite_content' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['cite_content'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'allowHtml' => true, 'tl_class' => 'clr long'],
            'sql' => 'mediumtext NULL',
        ],
        'bib_content' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['bib_content'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr long'],
            'sql' => 'mediumtext NULL',
        ],
        'abstract' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['abstract'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr long'],
            'sql' => 'mediumtext NULL',
        ],
        'json_data' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['json_data'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr long'],
            'sql' => 'mediumtext NULL',
        ],
        'tags' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['tags'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'tl_class' => 'clr long'],
            'sql' => 'text NULL',
        ],
        'download_attachments' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['download_attachments'],
            'exclude' => true,
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
        'published' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['published'],
            'exclude' => true,
            'filter' => true,
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'trash' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['trash'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50', 'disabled' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @internal
 */
class tl_zotero_item extends Backend
{
}
