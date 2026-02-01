
<?php

namespace raum51\ContaoZoteroBundle\Service;

use Contao\Date;
use Contao\StringUtil;
use raum51\ContaoZoteroBundle\Model\ZoteroItemModel;
use raum51\ContaoZoteroBundle\Model\ZoteroCollectionModel;
use raum51\ContaoZoteroBundle\Model\ZoteroItemCollectionModel;

class ZoteroImporter
{
    /**
     * Persist collections to tl_zotero_collection (upsert by collectionKey+libraryId)
     */
    public function importCollections(string $libraryId, array $collections): void
    {
        foreach ($collections as $c) {
            $data = $c['data'] ?? [];
            if (!isset($data['key'])) {
                continue;
            }
            $model = \Database::getInstance()->prepare('SELECT id FROM tl_zotero_collection WHERE collectionKey=? AND libraryId=?')->limit(1)->execute($data['key'], $libraryId);
            if ($model->numRows) {
                \Database::getInstance()->prepare('UPDATE tl_zotero_collection SET title=?, parentKey=?, tstamp=? WHERE id=?')
                    ->execute(
                        (string)($data['name'] ?? ''),
                        (string)($data['parentCollection'] ?? ''),
                        time(),
                        $model->id
                    );
            } else {
                \Database::getInstance()->prepare('INSERT INTO tl_zotero_collection (tstamp, libraryId, collectionKey, parentKey, title) VALUES (?,?,?,?,?)')
                    ->execute(
                        time(),
                        $libraryId,
                        (string)$data['key'],
                        (string)($data['parentCollection'] ?? ''),
                        (string)($data['name'] ?? '')
                    );
            }
        }
    }

    /**
     * Persist items to tl_zotero_item (upsert by itemKey+libraryId)
     */
    public function importItems(string $libraryId, array $items): void
    {
        foreach ($items as $it) {
            $data = $it['data'] ?? [];
            if (!isset($data['key'])) {
                continue;
            }
            $authors = [];
            foreach (($data['creators'] ?? []) as $creator) {
                $authors[] = trim(($creator['lastName'] ?? '') . ', ' . ($creator['firstName'] ?? ''));
            }
            $year = '';
            if (!empty($data['date'])) {
                $year = substr((string)$data['date'], 0, 4);
            }

            $exists = \Database::getInstance()->prepare('SELECT id FROM tl_zotero_item WHERE itemKey=? AND libraryId=?')->limit(1)->execute($data['key'], $libraryId);
            if ($exists->numRows) {
                \Database::getInstance()->prepare('UPDATE tl_zotero_item SET tstamp=?, title=?, itemType=?, authors=?, year=?, abstract=?, doi=?, isbn=?, publisher=?, url=?, updatedAt=?, rawJson=? WHERE id=?')
                    ->execute(
                        time(),
                        (string)($data['title'] ?? ''),
                        (string)($data['itemType'] ?? ''),
                        json_encode($authors, JSON_UNESCAPED_UNICODE),
                        (string)$year,
                        (string)($data['abstractNote'] ?? ''),
                        (string)($data['DOI'] ?? ''),
                        (string)($data['ISBN'] ?? ''),
                        (string)($data['publisher'] ?? ''),
                        (string)($data['url'] ?? ''),
                        time(),
                        json_encode($it, JSON_UNESCAPED_UNICODE),
                        $exists->id
                    );
            } else {
                \Database::getInstance()->prepare('INSERT INTO tl_zotero_item (tstamp, libraryId, itemKey, itemType, title, authors, year, abstract, doi, isbn, publisher, url, updatedAt, rawJson) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute(
                        time(),
                        $libraryId,
                        (string)$data['key'],
                        (string)($data['itemType'] ?? ''),
                        (string)($data['title'] ?? ''),
                        json_encode($authors, JSON_UNESCAPED_UNICODE),
                        (string)$year,
                        (string)($data['abstractNote'] ?? ''),
                        (string)($data['DOI'] ?? ''),
                        (string)($data['ISBN'] ?? ''),
                        (string)($data['publisher'] ?? ''),
                        (string)($data['url'] ?? ''),
                        time(),
                        json_encode($it, JSON_UNESCAPED_UNICODE)
                    );
            }

            // item-collection mappings
            foreach (($data['collections'] ?? []) as $ckey) {
                $mm = \Database::getInstance()->prepare('SELECT id FROM tl_zotero_item_collection WHERE itemKey=? AND collectionKey=? AND libraryId=?')->limit(1)->execute($data['key'], $ckey, $libraryId);
                if (!$mm->numRows) {
                    \Database::getInstance()->prepare('INSERT INTO tl_zotero_item_collection (tstamp, libraryId, itemKey, collectionKey) VALUES (?,?,?,?)')
                        ->execute(time(), $libraryId, (string)$data['key'], (string)$ckey);
                }
            }
        }
    }
}
