<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_creator_map – Mapping Zotero-Autor (firstname, lastname) → tl_member.
 * Kein Menüeintrag; Pflege nur im Item-Editor (tl_zotero_item_creator).
 */

$GLOBALS['TL_DCA']['tl_zotero_creator_map'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'zotero_firstname,zotero_lastname' => 'unique',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['zotero_lastname', 'zotero_firstname'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['zotero_firstname', 'zotero_lastname', 'member_id'],
            'format' => '%s %s → %s',
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
        'default' => '{creator_legend},zotero_firstname,zotero_lastname,member_id',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'zotero_firstname' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_creator_map']['zotero_firstname'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'zotero_lastname' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_creator_map']['zotero_lastname'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'member_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_creator_map']['member_id'],
            'exclude' => true,
            'inputType' => 'select',
            'eval' => [
                'mandatory' => false,
                'includeBlankOption' => true,
                'chosen' => true,
                'tl_class' => 'w50',
            ],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'table' => 'tl_member', 'field' => 'id'],
        ],
    ],
];
