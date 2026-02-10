<?php

declare(strict_types=1);

/*
 * DCA labels for tl_zotero_library (English).
 */
$GLOBALS['TL_LANG']['tl_zotero_library'] = [
    'title_legend' => 'Label',
    'zotero_legend' => 'Zotero',
    'citation_legend' => 'Citation style',
    'sync_legend' => 'Synchronisation',
    'options_legend' => 'Options',
    'expert_legend' => 'Expert settings',
    'title' => ['Title', 'Library label'],
    'tstamp' => ['Last modified', 'Time of last change'],
    'sorting' => ['Sort order', 'Order'],
    'library_id' => ['Library ID', 'Zotero user or group ID'],
    'library_type' => ['Library type', 'user or group'],
    'api_key' => ['API key', 'Zotero API key (secret)'],
    'citation_style' => ['Citation style', 'e.g. CSL URL or name'],
    'citation_locale' => ['Citation locale', 'e.g. de-DE, en-US'],
    'sync_interval' => ['Sync interval (seconds)', '0 = manual only'],
    'last_sync_at' => ['Last sync', 'Time of last successful sync'],
    'last_sync_status' => ['Last sync status', 'Success or error message'],
    'last_sync_version' => ['Last sync version', 'Zotero library version for incremental sync'],
    'download_attachments' => ['Attachments downloadable', 'Allow downloads at library level'],
    'sync' => ['Sync now', 'Run Zotero sync for this library'],
    'reset_sync' => ['Reset sync and run', 'Reset this library’s sync state and run a full sync immediately'],
    'collections' => ['Collections', 'Edit collections of this library'],
    'items' => ['Items', 'Edit items (publications) of this library'],
];
