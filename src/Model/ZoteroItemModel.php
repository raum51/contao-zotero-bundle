<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Model;

use Contao\Model;

/**
 * Contao-Model für tl_zotero_item.
 *
 * Liegt unter src/Model/, da Contao Models dort erwartet (TL_MODELS-Registrierung in config.php).
 * Wird für Reader-Modul (findPublishedByParentAndIdOrAlias) und optional ContentUrlResolver benötigt.
 */
class ZoteroItemModel extends Model
{
    protected static $strTable = 'tl_zotero_item';

    /**
     * Findet ein publiziertes Item per Library-ID (pid) und Alias oder ID.
     *
     * @param int|string $val Alias oder numerische ID
     * @param int        $pid Library-ID (tl_zotero_library.id)
     */
    public static function findPublishedByParentAndIdOrAlias(int|string $val, int $pid): self|null
    {
        $t = static::$strTable;
        $alias = is_numeric($val) ? null : $val;
        $id = is_numeric($val) ? (int) $val : null;

        $columns = ["$t.pid = ?", "$t.published = ?", "$t.trash = ?"];
        $values = [$pid, '1', '0'];

        if ($alias !== null) {
            $columns[] = "$t.alias = ?";
            $values[] = $alias;
        } else {
            $columns[] = "$t.id = ?";
            $values[] = $id;
        }

        return static::findOneBy($columns, $values, ['order' => "$t.id ASC"]);
    }

    /**
     * Findet ein publiziertes Item per Alias oder ID in einer der angegebenen Libraries.
     *
     * @param int|string   $val         Alias oder numerische ID
     * @param list<int>    $libraryIds  Library-IDs (tl_zotero_library.id)
     */
    public static function findPublishedByParentAndIdOrAliasInLibraries(int|string $val, array $libraryIds): self|null
    {
        if ($libraryIds === []) {
            return null;
        }

        foreach ($libraryIds as $pid) {
            $found = static::findPublishedByParentAndIdOrAlias($val, $pid);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
