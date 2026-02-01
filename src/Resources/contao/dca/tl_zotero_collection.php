
<?php

$GLOBALS['TL_DCA']['tl_zotero_collection'] = [
  'config' => [
    'dataContainer' => 'Table',
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'collectionKey' => 'index',
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
      'fields' => ['title','collectionKey'],
      'format' => '%s <span style="color:#999">[%s]</span>'
    ]
  ],
  'palettes' => [
    'default' => '{title_legend},title,collectionKey,parentKey,libraryId,updatedAt'
  ],
  'fields' => [
    'id' => [ 'sql' => "int(10) unsigned NOT NULL auto_increment" ],
    'tstamp' => [ 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'libraryId' => [ 'label'=>['Library',''], 'inputType'=>'text', 'eval'=>['readonly'=>true], 'sql' => "varchar(64) NOT NULL default ''" ],
    'collectionKey' => [ 'label'=>['Collection Key',''], 'inputType'=>'text', 'eval'=>['readonly'=>true], 'sql' => "varchar(64) NOT NULL default ''" ],
    'parentKey' => [ 'label'=>['Parent Key',''], 'inputType'=>'text', 'sql' => "varchar(64) NOT NULL default ''" ],
    'title' => [ 'label'=>['Titel',''], 'inputType'=>'text', 'sql' => "varchar(255) NOT NULL default ''" ],
    'updatedAt' => [ 'label'=>['Updated',''], 'inputType'=>'text', 'eval'=>['rgxp'=>'datim'], 'sql' => "int(10) unsigned NOT NULL default '0'" ]
  ]
];
