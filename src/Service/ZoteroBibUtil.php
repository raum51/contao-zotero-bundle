<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

/**
 * Hilfsfunktionen für BibTeX-Inhalte (z. B. cite_key aus bib_content).
 *
 * Liegt in src/Service/, da es von ZoteroSyncService und dem Alias-Callback genutzt wird.
 */
final class ZoteroBibUtil
{
    /**
     * Cite-Key aus dem von Zotero gelieferten BibTeX-String extrahieren.
     *
     * Format: @article{cite_key, ...} bzw. @book{cite_key, ...}
     *
     * @return string Der cite_key (z. B. "najm_associations_2020") oder '' wenn nicht gefunden
     */
    public static function extractCiteKeyFromBib(string $bibContent): string
    {
        if ($bibContent === '') {
            return '';
        }
        if (preg_match('/@\w+\{\s*([^,\s]+)\s*,/u', trim($bibContent), $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * Alias für DB/URL normalisieren: nur Zeichen erlauben, die als Alias unkritisch sind.
     */
    public static function sanitizeAlias(string $alias): string
    {
        $s = preg_replace('/[^a-zA-Z0-9_\-]/', '', $alias);

        return $s === '' ? '' : $s;
    }
}
