
<?php

// Register models
$GLOBALS['TL_MODELS']['tl_zotero_item'] = aum51\ContaoZoteroBundle\Model\ZoteroItemModel::class;
$GLOBALS['TL_MODELS']['tl_zotero_collection'] = aum51\ContaoZoteroBundle\Model\ZoteroCollectionModel::class;
$GLOBALS['TL_MODELS']['tl_zotero_library'] = aum51\ContaoZoteroBundle\Model\ZoteroLibraryModel::class;
$GLOBALS['TL_MODELS']['tl_zotero_item_collection'] = aum51\ContaoZoteroBundle\Model\ZoteroItemCollectionModel::class;

// Backend module (manage tables)
$GLOBALS['BE_MOD']['zotero'] = [
    'libraries' => [
        'tables' => ['tl_zotero_library']
    ],
    'items' => [
        'tables' => ['tl_zotero_item']
    ],
    'collections' => [
        'tables' => ['tl_zotero_collection']
    ],
];

// Frontend modules (legacy registration as fallback)
$GLOBALS['FE_MOD']['application']['zotero_list'] = aum51\ContaoZoteroBundle\FrontendModule\ZoteroListModule::class;
$GLOBALS['FE_MOD']['application']['zotero_reader'] = aum51\ContaoZoteroBundle\FrontendModule\ZoteroReaderModule::class;
