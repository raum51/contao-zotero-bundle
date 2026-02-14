<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_content']['title'] = ['Titel', 'Hier können Sie einen optionalen Titel für das Inhaltselement eingeben.'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item'] = ['Zotero-Einzelelement', 'Ein einzelnes Zotero-Item (fix oder aus URL)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode'] = ['Modus', 'Fest gewähltes Item oder Item aus URL (Reader)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode_options'] = [
    'fixed' => 'Fest gewähltes Item',
    'from_url' => 'Item aus URL (Reader)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_id'] = ['Zotero-Item', 'Das anzuzeigende Item'];
$GLOBALS['TL_LANG']['tl_content']['zotero_libraries'] = ['Zotero-Libraries', 'Libraries, in denen das Item gesucht wird (Modus „Item aus URL“)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template_options'] = [
    'cite_content' => 'Literaturverweis (cite_content)',
    'json_dl' => 'Metadaten als Liste (json_dl)',
    'fields' => 'Ausgewählte Felder (fields)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_items'] = ['Zotero-Items (einzeln/ausgewählt)', 'Ein oder mehrere Items anzeigen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'] = ['Publikationen eines Members', 'Alle mit diesem Contao-Mitglied verknüpften Publikationen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'] = ['Publikationen einer Collection', 'Alle Items der gewählten Zotero-Collection'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member'] = ['Contao-Mitglied', 'Mitglied, dessen Publikationen angezeigt werden'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection'] = ['Zotero-Collection', 'Collection, deren Items angezeigt werden'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template'] = ['Template', 'z. B. cite_content, json_dl, fields'];

// Zotero-Liste (CE)
$GLOBALS['TL_LANG']['tl_content']['config_legend'] = 'Listen-Einstellungen';
$GLOBALS['TL_LANG']['tl_content']['zotero_list'] = ['Zotero-Liste', 'Publikationsliste aus Zotero-Bibliothek'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collections'] = ['Collections', 'Optional: nur diese Collections anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_types'] = ['Item-Typen', 'Optional: nur diese Typen anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_author'] = ['Autor', 'Optional: nur Publikationen dieses Mitglieds anzeigen'];
$GLOBALS['TL_LANG']['tl_content']['zotero_reader_element'] = ['Reader-Element', 'Zotero-Einzelelement (aus URL) für Detailansicht auf derselben Seite'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_element'] = ['Such-Element', 'Zotero-Such-CE für Libraries und Such-Konfiguration beim Suchmodus'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_module'] = ['Such-Modul', 'Alternativ: Zotero-Such-Modul (falls kein Such-CE eingesetzt wird)'];

// Zotero-Suche/Filter (CE)
$GLOBALS['TL_LANG']['tl_content']['zotero_search'] = ['Zotero-Suche/Filter', 'Suchformular und Filter für Publikationen'];
$GLOBALS['TL_LANG']['tl_content']['search_config_legend'] = 'Such-Konfiguration';
$GLOBALS['TL_LANG']['tl_content']['zotero_search_enabled'] = ['Suchfeld aktivieren', 'Ohne Häkchen: Nur Filter (Autor, Jahr, Item-Typ), kein Suchfeld'];
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
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_item_type'] = ['Filter Item-Typ anzeigen', 'Dropdown für Item-Typ im Suchformular'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_fields'] = ['Durchsuchbare Felder', 'Reihenfolge = Priorität (z. B. title,tags,abstract)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode'] = ['Token-Logik', 'AND: alle Begriffe; OR: mindestens einer; Frontend: Nutzer wählt'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode_options'] = ['and' => 'AND (alle Begriffe)', 'or' => 'OR (mindestens ein Begriff)', 'frontend' => 'Im Frontend wählbar'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_tokens'] = ['Max. Token-Anzahl', 'Begrenzung bei Mehrwort-Suche (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_results'] = ['Max. Trefferanzahl', 'Limit der Suchergebnisse (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_content']['numberOfItems'] = ['Anzahl Einträge', 'Anzahl der angezeigten Einträge (0 = alle)'];
$GLOBALS['TL_LANG']['tl_content']['perPage'] = ['Einträge pro Seite', 'Anzahl pro Seite bei Pagination (0 = keine Pagination)'];
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
    'item_type' => 'Item-Typ',
    'year' => 'Jahr',
];
