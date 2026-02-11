<?php

declare(strict_types=1);

/*
 * DCA tl_zotero_item – Zotero-Publikationen. Daten von Zotero; edit nur für Creator↔Member.
 * Operationen: show, edit, toggle. ctable => tl_zotero_item_creator.
 */

use Contao\Backend;
use Contao\DataContainer;
use Contao\Database;
use Contao\System;
use Exception;
use Raum51\ContaoZoteroBundle\Service\ZoteroBibUtil;

$GLOBALS['TL_DCA']['tl_zotero_item'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'ptable' => 'tl_zotero_library',
        'ctable' => ['tl_zotero_item_creator'],
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
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['title'],
            'headerFields' => ['title'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title', 'year'],
            'format' => '%s (%s)',
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
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{zotero_legend},zotero_key,alias,zotero_version,title,item_type,year,date,publication_title;{content_legend},cite_content,bib_content;{data_legend},json_data,tags;{options_legend},download_attachments,published',
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
            'eval' => ['rgxp' => 'alias', 'doNotCopy' => true, 'unique' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'save_callback' => [
                ['tl_zotero_item', 'generateAlias'],
            ],
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
            'eval' => ['maxlength' => 512, 'tl_class' => 'long'],
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
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'published' => [
            'label' => &$GLOBALS['TL_LANG']['tl_zotero_item']['published'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50', 'toggle' => true],
            'sql' => "char(1) NOT NULL default '1'",
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
    /**
     * Auto-generate the item alias if it has not been set yet (cite_key from bib_content or title).
     * Uses Contao slug service for URL-safe string and aliasExists for duplicate check.
     *
     * @param mixed $varValue
     *
     * @throws Exception
     */
    public function generateAlias($varValue, DataContainer $dc): string
    {
        $aliasExists = static function (string $alias) use ($dc): bool {
            $result = Database::getInstance()
                ->prepare('SELECT id FROM tl_zotero_item WHERE alias=? AND id!=?')
                ->execute($alias, $dc->id);

            return $result->numRows > 0;
        };

        $varValue = trim((string) $varValue);

        if ($varValue === '') {
            $source = '';
            if (!empty($dc->activeRecord->bib_content)) {
                $source = ZoteroBibUtil::extractCiteKeyFromBib((string) $dc->activeRecord->bib_content);
            }
            if ($source === '' && !empty($dc->activeRecord->title)) {
                $source = (string) $dc->activeRecord->title;
            }
            if ($source === '') {
                $source = 'item-' . $dc->id;
            }
            $varValue = System::getContainer()->get('contao.slug')->generate($source, [], $aliasExists);

            return $varValue;
        }

        if (preg_match('/^[1-9]\d*$/', $varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'] ?? 'Alias "%s" is numeric.', $varValue));
        }

        if ($aliasExists($varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'] ?? 'Alias "%s" already exists.', $varValue));
        }

        return $varValue;
    }
}
