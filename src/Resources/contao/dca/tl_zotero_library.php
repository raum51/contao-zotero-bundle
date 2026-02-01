
<?php

$GLOBALS['TL_DCA']['tl_zotero_library'] = [
  'config' => [
    'dataContainer' => 'Table',
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'enabled' => 'index'
      ]
    ]
  ],
  'list' => [
    'sorting' => [
      'mode' => 1,
      'fields' => ['title']
    ],
    'label' => [
      'fields' => ['title', 'type', 'libraryId'],
      'format' => '%s <span style="color:#999">[%s:%s]</span>'
    ],
    'global_operations' => [
      'all' => [
        'label' => ['Alle markieren', ''],
        'href' => 'act=select',
        'class' => 'header_edit_all',
        'attributes' => 'onclick="Backend.getScrollOffset()"'
      ]
    ],
    'operations' => [
      'edit' => ['label' => ['Bearbeiten',''], 'href' => 'act=edit', 'icon' => 'edit.svg'],
      'delete' => ['label' => ['Löschen',''], 'href' => 'act=delete', 'icon' => 'delete.svg', 'attributes' => 'onclick="if(!confirm('Wirklich löschen?'))return false;Backend.getScrollOffset()"'],
      'show' => ['label' => ['Details',''], 'href' => 'act=show', 'icon' => 'show.svg']
    ]
  ],
  'palettes' => [
    '__selector__' => [],
    'default' => '{title_legend},title,enabled;{zotero_legend},type,libraryId,apiKey;{sync_legend},lastSync,etag,lastModified'
  ],
  'fields' => [
    'id' => [ 'sql' => "int(10) unsigned NOT NULL auto_increment" ],
    'tstamp' => [ 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'title' => [
      'label' => ['Titel',''],
      'inputType' => 'text',
      'eval' => ['mandatory'=>true, 'maxlength'=>255],
      'sql' => "varchar(255) NOT NULL default ''"
    ],
    'enabled' => [
      'label' => ['Aktiviert',''],
      'inputType' => 'checkbox',
      'eval' => ['tl_class'=>'w50'],
      'sql' => "char(1) NOT NULL default ''"
    ],
    'type' => [
      'label' => ['Library-Typ',''],
      'inputType' => 'select',
      'options' => ['user','group'],
      'reference' => ['user' => 'User', 'group' => 'Group'],
      'eval' => ['mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'],
      'sql' => "varchar(16) NOT NULL default ''"
    ],
    'libraryId' => [
      'label' => ['User- oder Group-ID',''],
      'inputType' => 'text',
      'eval' => ['mandatory'=>true, 'maxlength'=>64, 'tl_class'=>'w50'],
      'sql' => "varchar(64) NOT NULL default ''"
    ],
    'apiKey' => [
      'label' => ['API Key',''],
      'inputType' => 'text',
      'eval' => ['mandatory'=>true, 'maxlength'=>128, 'tl_class'=>'clr'],
      'sql' => "varchar(128) NOT NULL default ''"
    ],
    'lastSync' => [ 'label' => ['Letzter Sync',''], 'inputType'=>'text', 'eval'=>['rgxp'=>'datim', 'datepicker'=>true], 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'etag' => [ 'label' => ['ETag',''], 'inputType'=>'text', 'eval'=>['maxlength'=>255], 'sql' => "varchar(255) NOT NULL default ''" ],
    'lastModified' => [ 'label' => ['Last-Modified',''], 'inputType'=>'text', 'eval'=>['maxlength'=>255], 'sql' => "varchar(255) NOT NULL default ''" ]
  ]
];
