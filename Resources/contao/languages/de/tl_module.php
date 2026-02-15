<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_module']['zotero_list'] = ['Zotero-Liste', 'Publikationsliste aus Zotero-Bibliothek'];
$GLOBALS['TL_LANG']['tl_module']['zotero_reader'] = ['Zotero-Lese', 'Detailansicht einer Publikation'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search'] = ['Zotero-Suche', 'Suchformular für Publikationen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_libraries'] = ['Zotero-Bibliotheken', 'Bibliotheken für dieses Modul (analog zu News-Archiven)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_collections'] = ['Collections', 'Optional: nur diese Collections anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_item_types'] = ['Publikations-Typen', 'Optional: nur diese Typen anzeigen (leer = alle)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_order'] = ['Sortierung', 'Reihenfolge der Einträge'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_order_options'] = [
    'order_author_date' => 'Autor (erstgenannt), Publikationsdatum',
    'order_year_author' => 'Jahr, Autor (erstgenannt)',
    'order_title' => 'Titel',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date'] = ['Sortierrichtung Datum/Jahr', 'Gilt für Sortierung und Gruppierung wenn Datum/Jahr betroffen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date_options'] = [
    'asc' => 'Aufsteigend (älteste zuerst)',
    'desc' => 'Absteigend (neueste zuerst)',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_group'] = ['Gruppierung', 'Liste gruppiert anzeigen (leer = keine Gruppierung)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_group_options'] = [
    'library' => 'Library',
    'collection' => 'Collection',
    'item_type' => 'Publikations-Typ',
    'year' => 'Jahr',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_page'] = ['Zielseite Listen-Modul', 'Seite mit Zotero-Listen-Modul für Suchergebnisse'];
$GLOBALS['TL_LANG']['tl_module']['zotero_template'] = ['Publikations-Template', 'Darstellungsform pro Eintrag'];
$GLOBALS['TL_LANG']['tl_module']['zotero_reader_module'] = ['Lesemodul', 'Optional: Bei Auswahl rendert die Liste bei Klick auf eine Publikation das Lesemodul (Liste und Detail auf derselben Seite). Sonst: Weiterleitung zur jumpTo-Seite der Library.'];
$GLOBALS['TL_LANG']['tl_module']['zotero_template_options'] = [
    'cite_content' => 'Literaturverweis (cite_content)',
    'json_dl' => 'Metadaten als Liste (json_dl)',
    'fields' => 'Ausgewählte Felder (fields)',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_search'] = ['Zotero-Suche/Filter', 'Suchformular und Filter für Publikationen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_module'] = ['Such-Modul', 'Optional: Beim Suchmodus Libraries und Such-Konfiguration von diesem Modul übernehmen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_enabled'] = ['Suchfeld aktivieren', 'Ohne Häkchen: Nur Filter, kein Suchfeld'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_sort_by_weight'] = ['Nach Relevanz sortieren', 'Bei Suche: Nach Gewicht sortieren'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_title'] = ['Gewicht Titel', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_creators'] = ['Gewicht Creators', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_tags'] = ['Gewicht Tags', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_publication_title'] = ['Gewicht Publication Title', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_year'] = ['Gewicht Jahr', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_abstract'] = ['Gewicht Abstract', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_zotero_key'] = ['Gewicht Zotero-Key', '0 = nicht durchsuchen'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_author'] = ['Filter Autor anzeigen', 'Dropdown für Autor-Filter im Suchformular'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_year'] = ['Filter Jahr anzeigen', 'Felder Jahr von/bis im Suchformular'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_item_type'] = ['Filter Publikations-Typ anzeigen', 'Dropdown für Publikations-Typ im Suchformular'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_fields'] = ['Durchsuchbare Felder', 'Reihenfolge = Priorität (z. B. title,tags,abstract)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode'] = ['Token-Logik', 'AND: alle Begriffe müssen vorkommen; OR: mindestens einer'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_query_type_label'] = 'Token-Logik';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode_options'] = ['and' => 'AND (alle Begriffe)', 'or' => 'OR (mindestens ein Begriff)', 'frontend' => 'Im Frontend wählbar'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_tokens'] = ['Max. Token-Anzahl', 'Begrenzung bei Mehrwort-Suche (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_results'] = ['Max. Trefferanzahl', 'Limit der Suchergebnisse (0 = unbegrenzt)'];
$GLOBALS['TL_LANG']['tl_module']['search_config_legend'] = 'Such-Konfiguration';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_author_label'] = 'Autor';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_author_all'] = '– Alle –';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_year_from'] = 'Jahr von';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_year_to'] = 'Jahr bis';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_results'] = 'Suchergebnisse';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_no_results'] = 'Keine Publikationen gefunden.';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_limit_exceeded'] = 'Ihre Suche wurde auf %s Begriffe gekürzt. Weitere Begriffe wurden ignoriert.';
