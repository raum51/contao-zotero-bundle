<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\Backend;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Doctrine\DBAL\Connection;

/**
 * Zeigt auf der Backend-Startseite einen Hinweis, wenn Zotero-Libraries
 * mit Sync-Fehlern (last_sync_status = ERROR:...) existieren.
 *
 * RUNNING wird ausgeschlossen (läuft noch oder abgestürzt, keine Fehlermeldung).
 * Nutzt den getSystemMessages-Hook – siehe [Contao Developer Docs](https://docs.contao.org/dev/reference/hooks/getSystemMessages).
 */
#[AsHook('getSystemMessages')]
final class GetSystemMessagesSyncWarningListener
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): string
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title, last_sync_status FROM tl_zotero_library WHERE last_sync_status != '' AND last_sync_status != 'OK' AND last_sync_status != 'RUNNING' ORDER BY title"
        );

        if ($rows === []) {
            return '';
        }

        $lang = $GLOBALS['TL_LANG']['tl_zotero_library'] ?? [];
        $heading = $lang['sync_error_title'] ?? 'Zotero-Sync – Fehler';
        $linkLabel = $GLOBALS['TL_LANG']['MOD']['bibliography'][0] ?? 'Bibliotheken';

        $count = \count($rows);
        $titles = [];
        foreach (\array_slice($rows, 0, 5) as $row) {
            $titles[] = htmlspecialchars((string) ($row['title'] ?? 'ID ' . $row['id']));
        }
        $suffix = $count > 5 ? ' (und ' . ($count - 5) . ' weitere)' : '';

        $href = Backend::addToUrl('do=bibliography');
        $html = '<p class="tl_error">'
            . '<strong>' . htmlspecialchars($heading) . '</strong> – '
            . $count . ' Bibliothek(en) mit Sync-Fehlern: '
            . implode(', ', $titles) . $suffix . '. '
            . '<a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($linkLabel) . '</a> öffnen und „Letzter Sync-Status“ prüfen.'
            . '</p>';

        return $html;
    }
}
