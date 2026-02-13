<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_library – Konfiguration pro Zotero-Bibliothek.
 * Operationen: show, edit, copy, delete.
 */

$GLOBALS['TL_DCA']['tl_zotero_library'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ctable' => ['tl_zotero_collection', 'tl_zotero_item'],
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['title'],
            'flag' => 1,
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
            'sync_all' => [
                'href' => 'key=zotero_sync_all',
                'class' => 'header_sync',
                'icon' => 'sync.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['sync_all'],
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['tl_zotero_library']['sync_all_confirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"',
            ],
            'reset_sync_all' => [
                'href' => 'key=zotero_reset_sync_all',
                'class' => 'header_reset_sync',
                'icon' => 'sync.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['reset_sync_all'],
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['tl_zotero_library']['sync_all_confirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"',
            ],
        ],
        'operations' => [
            'toggle' => [
                'href' => 'act=toggle&amp;field=published',
                'icon' => 'visible.svg',
                'showInHeader' => true,
                'primary' => true,
            ],
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'sync' => [
                'href' => 'key=zotero_sync',
                'icon' => 'sync.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['sync'],
            ],
            'collections' => [
                'href' => 'table=tl_zotero_collection',
                'icon' => 'folderC.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['collections'],
            ],
            'items' => [
                'href' => 'table=tl_zotero_item',
                'icon' => 'article.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['items'],
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
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
        'default' => '{title_legend},title;{frontend_legend},jumpTo;{zotero_legend},library_id,library_type,api_key;{citation_legend},citation_style,citation_locale,cite_content_markup;{sync_legend},sync_interval,last_sync_at,last_sync_status,last_sync_version;{options_legend},download_attachments,published;{expert_legend},sorting',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['tstamp'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'sorting' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['sorting'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'library_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['library_id'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'library_type' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['library_type'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['user' => 'user', 'group' => 'group'],
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default 'user'",
        ],
        'api_key' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['api_key'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'hideInput' => true, 'tl_class' => 'long'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'citation_style' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['citation_style'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'citation_locale' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['citation_locale'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'cite_content_markup' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['cite_content_markup'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => [
                'unchanged' => 'unchanged',
                'remove_divs' => 'remove_divs',
                'remove_all' => 'remove_all',
            ],
            'reference' => &$GLOBALS['TL_LANG']['tl_zotero_library']['cite_content_markup_options'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default 'unchanged'",
        ],
        'sync_interval' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['sync_interval'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'last_sync_at' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['last_sync_at'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'datim', 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'last_sync_status' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['last_sync_status'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'long'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'last_sync_version' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['last_sync_version'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'download_attachments' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['download_attachments'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'published' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['published'],
            'exclude' => true,
            'filter' => true,
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'jumpTo' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_library']['jumpTo'],
            'exclude' => true,
            'inputType' => 'pageTree',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'table' => 'tl_page', 'field' => 'id'],
        ],
    ],
];
