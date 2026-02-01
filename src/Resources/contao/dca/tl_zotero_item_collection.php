
<?php

$GLOBALS['TL_DCA']['tl_zotero_item_collection'] = [
  'config' => [
    'dataContainer' => 'Table',
    'closed' => true,
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'itemKey,collectionKey,libraryId' => 'index'
      ]
    ]
  ],
  'fields' => [
    'id' => [ 'sql' => "int(10) unsigned NOT NULL auto_increment" ],
    'tstamp' => [ 'sql' => "int(10) unsigned NOT NULL default '0'" ],
    'libraryId' => [ 'sql' => "varchar(64) NOT NULL default ''" ],
    'itemKey' => [ 'sql' => "varchar(64) NOT NULL default ''" ],
    'collectionKey' => [ 'sql' => "varchar(64) NOT NULL default ''" ]
  ]
];
