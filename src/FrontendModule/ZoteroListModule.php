
<?php

namespace raum51\ContaoZoteroBundle\FrontendModule;

use Contao\Module;
use Contao\FrontendTemplate;
use raum51\ContaoZoteroBundle\Model\ZoteroItemModel;

class ZoteroListModule extends Module
{
    protected $strTemplate = 'mod_zotero_list';

    protected function compile(): void
    {
        $limit = 50;
        $db = \Database::getInstance();
        $items = $db->prepare('SELECT * FROM tl_zotero_item ORDER BY year DESC, title ASC LIMIT ' . (int)$limit)->execute();
        $rows = [];
        while ($items->next()) {
            $rows[] = $items->row();
        }
        $this->Template->items = $rows;
    }
}
