<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_content']['title'] = ['Titel', 'Hier können Sie einen optionalen Titel für das Inhaltselement eingeben.'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item'] = ['Zotero-Einzelelement', 'Eine einzelne Publikation (fix oder aus URL)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode'] = ['Modus', 'Fest gewählte Publikation oder Publikation aus URL (Reader)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode_options'] = [
    'fixed' => 'Fest gewählte Publikation',
    'from_url' => 'Publikation aus URL (Reader)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_id'] = ['Publikation', 'Die anzuzeigende Publikation'];
$GLOBALS['TL_LANG']['tl_content']['zotero_libraries'] = ['Zotero-Libraries', 'Libraries, in denen die Publikation gesucht wird (Modus „Publikation aus URL“)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template_options'] = [
    'cite_content' => 'Literaturverweis (cite_content)',
    'json_dl' => 'Metadaten als Liste (json_dl)',
    'fields' => 'Ausgewählte Felder (fields)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_items'] = ['Zotero-Publikationen (einzeln/ausgewählt)', 'Eine oder mehrere Publikationen anzeigen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'] = ['Publikationen eines Members', 'Alle mit diesem Contao-Mitglied verknüpften Publikationen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'] = ['Publikationen einer Collection', 'Alle Publikationen der gewählten Zotero-Collection'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member'] = ['Contao-Mitglied', 'Mitglied, dessen Publikationen angezeigt werden'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection'] = ['Zotero-Collection', 'Collection, deren Publikationen angezeigt werden'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template'] = ['Template', 'z. B. cite_content, json_dl, fields'];

// Zotero-Creator-Items (CE)
$GLOBALS['TL_LANG']['tl_content']['zotero_creator_items'] = ['Zotero-Creator-Publikationen', 'Publikationen eines Contao-Mitglieds (fix oder aus URL)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member_mode'] = ['Modus', 'Mitglied fest gewählt oder aus URL (Pfad)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member_mode_options'] = [
    'fixed' => 'Mitglied fest gewählt',
    'from_url' => 'Mitglied aus URL (Pfad) – für Member-Detailseiten',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_creator_items_no_publications'] = 'Keine Publikationen';

// Zotero-Liste (CE)
$GLOBALS['TL_LANG']['tl_content']['config_legend'] = 'Listen-Einstellungen';
$GLOBALS['TL_LANG']['tl_content']['zotero_list'] = ['Zotero-Liste', 'Publikationsliste aus Zotero-Bibliothek'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collections'] = ['Collections', 'Optional: nur diese Collections anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_types'] = ['Publikations-Typen', 'Optional: nur diese Typen anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_author'] = ['Autor', 'Optional: nur Publikationen dieses Mitglieds anzeigen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_reader_element'] = ['Reader-Element', 'Zotero-Einzelelement (aus URL) für Detailansicht auf derselben Seite'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_element'] = ['Such-Element', 'Zotero-Such-CE für Libraries und Such-Konfiguration beim Suchmodus'];
// Zotero-Suche/Filter (CE)
$GLOBALS['TL_LANG']['tl_content']['zotero_search'] = ['Zotero-Suche/Filter', 'Suchformular und Filter für Publikationen'];
$GLOBALS['TL_LANG']['tl_content']['search_config_legend'] = 'Such-Konfiguration';
$GLOBALS['TL_LANG']['tl_content']['zotero_search_enabled'] = ['Suchfeld aktivieren', 'Ohne Häkchen: Nur Filter (Autor, Jahr, Publikations-Typ), kein Suchfeld'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_sort_by_weight'] = ['Nach Relevanz sortieren', 'Bei Suche: Nach Gewicht sortieren (sonst Listen-Sortierung/Gruppierung)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_title'] = ['Gewicht Titel', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_creators'] = ['Gewicht Creators', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_tags'] = ['Gewicht Tags', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_publication_title'] = ['Gewicht Publication Title', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_year'] = ['Gewicht Jahr', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_abstract'] = ['Gewicht Abstract', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_zotero_key'] = ['Gewicht Zotero-Key', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_page'] = ['Zielseite Listen-Element', 'Seite mit Zotero-Liste für Suchergebnisse'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_author'] = ['Filter Autor anzeigen', 'Dropdown für Autor-Filter im Suchformular'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_year'] = ['Filter Jahr anzeigen', 'Felder Jahr von/bis im Suchformular'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_item_type'] = ['Filter Publikations-Typ anzeigen', 'Dropdown für Publikations-Typ im Suchformular'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_fields'] = ['Durchsuchbare Felder', 'Reihenfolge = Priorität (z. B. title,tags,abstract)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode'] = ['Token-Logik', 'AND: alle Begriffe; OR: mindestens einer; Frontend: Nutzer wählt'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode_options'] = ['and' => 'AND (alle Begriffe)', 'or' => 'OR (mindestens ein Begriff)', 'frontend' => 'Im Frontend wählbar'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_tokens'] = ['Max. Token-Anzahl', 'Begrenzung bei Mehrwort-Suche (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_results'] = ['Max. Trefferanzahl', 'Limit der Suchergebnisse (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_content']['numberOfItems'] = ['Anzahl Publikationen', 'Anzahl der angezeigten Publikationen (0 = alle)'];
$GLOBALS['TL_LANG']['tl_content']['perPage'] = ['Publikationen pro Seite', 'Anzahl pro Seite bei Pagination (0 = keine Pagination)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_order'] = ['Sortierung', 'Reihenfolge der Einträge'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_order_options'] = [
    'order_author_date' => 'Autor (erstgenannt), Publikationsdatum',
    'order_year_author' => 'Jahr, Autor (erstgenannt)',
    'order_title' => 'Titel',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date'] = ['Sortierrichtung Datum/Jahr', 'Gilt für Sortierung und Gruppierung'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date_options'] = [
    'asc' => 'Aufsteigend (älteste zuerst)',
    'desc' => 'Absteigend (neueste zuerst)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_group'] = ['Gruppierung', 'Liste gruppiert anzeigen (leer = keine Gruppierung)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_group_options'] = [
    'library' => 'Library',
    'collection' => 'Collection',
    'item_type' => 'Publikations-Typ',
    'year' => 'Jahr',
];
