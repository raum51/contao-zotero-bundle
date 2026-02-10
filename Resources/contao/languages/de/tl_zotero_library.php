<?php

declare(strict_types=1);

/*
 * DCA-Labels für tl_zotero_library (Deutsch).
 */
$GLOBALS['TL_LANG']['tl_zotero_library'] = [
    'title_legend' => 'Bezeichnung',
    'zotero_legend' => 'Zotero',
    'citation_legend' => 'Zitierstil',
    'sync_legend' => 'Synchronisation',
    'options_legend' => 'Optionen',
    'expert_legend' => 'Experten-Einstellungen',
    'title' => ['Titel', 'Bezeichnung der Bibliothek'],
    'tstamp' => ['Änderungsdatum', 'Zeitpunkt der letzten Änderung'],
    'sorting' => ['Sortierung', 'Reihenfolge'],
    'library_id' => ['Library-ID', 'Zotero User- oder Group-ID'],
    'library_type' => ['Bibliothekstyp', 'user oder group'],
    'api_key' => ['API-Key', 'Zotero API-Key (geheim)'],
    'citation_style' => ['Zitierstil', 'z. B. CSL-URL oder -Name'],
    'citation_locale' => ['Zitier-Locale', 'z. B. de-DE, en-US'],
    'sync_interval' => ['Sync-Intervall (Sekunden)', '0 = nur manuell'],
    'last_sync_at' => ['Letzter Sync', 'Zeitpunkt des letzten erfolgreichen Syncs'],
    'last_sync_status' => ['Letzter Sync-Status', 'Erfolg oder Fehlermeldung'],
    'last_sync_version' => ['Letzte Sync-Version', 'Zotero Library-Version für inkrementellen Sync'],
    'download_attachments' => ['Attachments herunterladbar', 'Downloads auf Library-Ebene erlauben'],
    'published' => ['Veröffentlichen', 'Bibliothek im Frontend anzeigen'],
    'sync' => ['Jetzt synchronisieren', 'Zotero-Synchronisation für diese Bibliothek starten'],
    'reset_sync' => ['Synchronisation zurücksetzen und starten', 'Sync-Metadaten dieser Library zurücksetzen und sofort einen Voll-Sync ausführen'],
    'sync_all' => ['Alle publizierten synchronisieren', 'Sync für alle veröffentlichten Bibliotheken starten'],
    'reset_sync_all' => ['Alle publizierten zurücksetzen und synchronisieren', 'Sync-Metadaten aller veröffentlichten Bibliotheken zurücksetzen und Voll-Sync ausführen'],
    'sync_all_confirm' => 'Bei sehr umfangreichen Bibliotheken kann es zu Timeout-Problemen kommen. In solchen Fällen führen Sie den Sync (insbesondere den initialen Sync) am besten über die Kommandozeile aus: php bin/console contao:zotero:sync bzw. contao:zotero:sync --reset.\n\nTrotzdem jetzt im Backend starten?',
    'sync_all_timeout_hint' => 'Sync kann bei vielen Bibliotheken lange dauern. Bei Timeout: Sync einzeln pro Library oder per Kommandozeile (php bin/console contao:zotero:sync).',
    'collections' => ['Sammlungen', 'Sammlungen dieser Bibliothek bearbeiten'],
    'items' => ['Einträge', 'Einträge (Publikationen) dieser Bibliothek bearbeiten'],
    'sync_status_done' => 'Zotero-Sync abgeschlossen',
    'sync_status_done_with_title' => 'Sync Zotero-Library %s abgeschlossen',
    'sync_status_collections' => 'Collections: %d neu, %d aktualisiert',
    'sync_status_items' => 'Items: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen',
    'sync_status_collection_items' => 'Collection-Item-Zuordnungen: %d neu, %d gelöscht',
    'sync_status_item_creators' => 'Item-Creator-Zuordnungen: %d neu, %d gelöscht',
    'sync_error_invalid_id' => 'Zotero-Sync: Ungültige oder fehlende Library-ID.',
    'sync_error_failed' => 'Zotero-Sync fehlgeschlagen',
    'sync_error_title' => 'Zotero-Sync – Fehler',
    'sync_error_timeout_hint' => 'Bei großen Bibliotheken: Sync einzeln pro Library ausführen oder über die Kommandozeile (php bin/console contao:zotero:sync). Optional kann der Sync per Cronjob geplant werden – der Cronjob kann keine Meldung im Backend anzeigen, aber z. B. in eine Log-Datei schreiben.',
];
