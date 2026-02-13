<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_locales – Lokalisierte Zotero-Schema-Daten (Item-Typen, Item-Felder) pro Locale.
 * Wird per Command contao:zotero:fetch-locales befüllt. Kein ptable/ctable.
 * Locales: en_US (Fallback), de_DE, plus jede Library citation_locale und jeden Website-Root (Contao-Format).
 */

$GLOBALS['TL_DCA']['tl_zotero_locales'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'doNotCopyRecords' => true,
        'closed' => true,
        'notEditable' => true,
        'notCreatable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'locale' => 'unique',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['locale'],
            'headerFields' => ['locale'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['locale'],
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
        'default' => '{locale_legend},locale,tstamp;{data_legend},item_types,item_fields',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_locales']['tstamp'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'locale' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_locales']['locale'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 16, 'readonly' => true],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'item_types' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_locales']['item_types'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'style' => 'height:120px', 'preserveTags' => true],
            'load_callback' => [
                static function (mixed $value): string {
                    if (\is_string($value)) {
                        $decoded = json_decode($value, true);
                        return \is_array($decoded) ? json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) : $value;
                    }
                    return '';
                },
            ],
            'sql' => 'mediumtext NULL',
        ],
        'item_fields' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_locales']['item_fields'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly' => true, 'style' => 'height:120px', 'preserveTags' => true],
            'load_callback' => [
                static function (mixed $value): string {
                    if (\is_string($value)) {
                        $decoded = json_decode($value, true);
                        return \is_array($decoded) ? json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) : $value;
                    }
                    return '';
                },
            ],
            'sql' => 'mediumtext NULL',
        ],
    ],
];
