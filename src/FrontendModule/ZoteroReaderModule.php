
<?php

namespace raum51\ContaoZoteroBundle\FrontendModule;

use Contao\Module;

class ZoteroReaderModule extends Module
{
    protected $strTemplate = 'mod_zotero_detail';

    protected function compile(): void
    {
        $itemKey = (string)($_GET['item'] ?? '');
        if ($itemKey === '') {
            $this->Template->item = null;
            return;
        }
        $db = \Database::getInstance();
        $item = $db->prepare('SELECT * FROM tl_zotero_item WHERE itemKey=?')->limit(1)->execute($itemKey);
        $this->Template->item = $item->numRows ? $item->row() : null;
    }
}
