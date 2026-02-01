
<?php

$GLOBALS['TL_DCA']['tl_zotero_item'] = [
  'config' => [
    'dataContainer' => 'Table',
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'itemKey' => 'index',
        'libraryId' => 'index'
      ]
    ]
  ],
  'list' => [
    'sorting' => [
      'mode' => 1,
      'fields' => ['title']
    ],
    'label' => [
      'fields' => ['title','itemType','year'],
      'format' => '%s <span style="color:#999">[%s, %s]</span>'
    ],
    'operations' => [
      'edit' => ['label' => ['Bearbeiten',''], 'href' => 'act=edit', 'icon' => 'edit.svg'],
      'show' => ['label' => ['Details',''], 'href' => 'act=show', 'icon' => 'show.svg']
    ]
  ],
  'palettes' => [
    'default' => '{title_legend},title,itemKey,itemType,authors,year;{meta_legend},abstract,doi,isbn,publisher,url;{sync_legend},libraryId,updatedAt,rawJson'
  ],
  'fields' => [
    'id' => [ 'sql' => "int(10) unsigned NOT NULL auto_increment" ],
    'tstamp' => [ 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'libraryId' => [ 'label'=>['Library',''], 'inputType'=>'text', 'eval'=>['readonly'=>true], 'sql' => "varchar(64) NOT NULL default ''" ],
    'itemKey' => [ 'label'=>['Item Key',''], 'inputType'=>'text', 'eval'=>['readonly'=>true], 'sql' => "varchar(64) NOT NULL default ''" ],
    'itemType' => [ 'label'=>['Typ',''], 'inputType'=>'text', 'eval'=>['readonly'=>true], 'sql' => "varchar(64) NOT NULL default ''" ],
    'title' => [ 'label'=>['Titel',''], 'inputType'=>'text', 'eval'=>['maxlength'=>1024], 'sql' => "varchar(1024) NOT NULL default ''" ],
    'authors' => [ 'label'=>['Autoren (JSON)',''], 'inputType'=>'textarea', 'eval'=>['style'=>'height:60px'], 'sql' => "mediumtext NULL" ],
    'year' => [ 'label'=>['Jahr',''], 'inputType'=>'text', 'eval'=>['maxlength'=>4], 'sql' => "varchar(4) NOT NULL default ''" ],
    'abstract' => [ 'label'=>['Abstract',''], 'inputType'=>'textarea', 'sql' => "mediumtext NULL" ],
    'doi' => [ 'label'=>['DOI',''], 'inputType'=>'text', 'sql' => "varchar(255) NOT NULL default ''" ],
    'isbn' => [ 'label'=>['ISBN',''], 'inputType'=>'text', 'sql' => "varchar(32) NOT NULL default ''" ],
    'publisher' => [ 'label'=>['Publisher',''], 'inputType'=>'text', 'sql' => "varchar(255) NOT NULL default ''" ],
    'url' => [ 'label'=>['URL',''], 'inputType'=>'text', 'sql' => "varchar(1024) NOT NULL default ''" ],
    'updatedAt' => [ 'label'=>['Updated',''], 'inputType'=>'text', 'eval'=>['rgxp'=>'datim'], 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'rawJson' => [ 'label'=>['Rohdaten',''], 'inputType'=>'textarea', 'sql' => "longtext NULL" ]
  ]
];
