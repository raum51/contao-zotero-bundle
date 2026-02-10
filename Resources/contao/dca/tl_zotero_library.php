<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_library – Konfiguration pro Zotero-Bibliothek.
 * Operationen: show, edit, copy, delete.
 */

$GLOBALS['TL_DCA']['tl_zotero_library'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
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
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
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
        'default' => '{title_legend},title;{zotero_legend},library_id,library_type,api_key;{citation_legend},citation_style,citation_locale;{sync_legend},sync_interval,last_sync_at,last_sync_status,last_sync_version;{options_legend},download_attachments;{expert_legend},sorting',
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
    ],
];
