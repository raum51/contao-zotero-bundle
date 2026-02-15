<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_module']['zotero_list'] = ['Zotero list', 'Publication list from Zotero library'];
$GLOBALS['TL_LANG']['tl_module']['zotero_reader'] = ['Zotero reader', 'Detail view of a publication'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search'] = ['Zotero search', 'Search form for publications'];
$GLOBALS['TL_LANG']['tl_module']['zotero_libraries'] = ['Zotero libraries', 'Libraries for this module (like news archives)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_collections'] = ['Collections', 'Optional: show only these collections (empty = all)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_item_types'] = ['Publication types', 'Optional: show only these types (empty = all)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_order'] = ['Sort order', 'Order of entries'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_order_options'] = [
    'order_author_date' => 'Author (first), publication date',
    'order_year_author' => 'Year, author (first)',
    'order_title' => 'Title',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date'] = ['Sort direction date/year', 'Applies when sorting or grouping involves date/year'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_sort_direction_date_options'] = [
    'asc' => 'Ascending (oldest first)',
    'desc' => 'Descending (newest first)',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_group'] = ['Grouping', 'Display list grouped (empty = no grouping)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_group_options'] = [
    'library' => 'Library',
    'collection' => 'Collection',
    'item_type' => 'Publication type',
    'year' => 'Year',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_list_page'] = ['List module target page', 'Page with Zotero list module for search results'];
$GLOBALS['TL_LANG']['tl_module']['zotero_template'] = ['Publication template', 'Display format per entry'];
$GLOBALS['TL_LANG']['tl_module']['zotero_reader_module'] = ['Reader module', 'Optional: If set, the list renders the reader module when a publication is clicked (list and detail on the same page). Otherwise: redirect to the library jumpTo page.'];
$GLOBALS['TL_LANG']['tl_module']['zotero_template_options'] = [
    'cite_content' => 'Citation (cite_content)',
    'json_dl' => 'Metadata as list (json_dl)',
    'fields' => 'Selected fields (fields)',
];
$GLOBALS['TL_LANG']['tl_module']['zotero_search'] = ['Zotero Search/Filter', 'Search form and filter for publications'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_module'] = ['Search module', 'Optional: In search mode, use libraries and search config from this module'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_enabled'] = ['Enable search field', 'Unchecked: Filter only, no search field'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_sort_by_weight'] = ['Sort by relevance', 'When searching: sort by weight'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_title'] = ['Weight title', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_creators'] = ['Weight creators', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_tags'] = ['Weight tags', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_publication_title'] = ['Weight publication title', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_year'] = ['Weight year', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_abstract'] = ['Weight abstract', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_weight_zotero_key'] = ['Weight Zotero key', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_author'] = ['Show author filter', 'Author dropdown in search form'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_year'] = ['Show year filter', 'Year from/to fields in search form'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_show_item_type'] = ['Show publication type filter', 'Publication type dropdown in search form'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_fields'] = ['Searchable fields', 'Order = priority (e.g. title,tags,abstract)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode'] = ['Token logic', 'AND: all terms must match; OR: at least one'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_query_type_label'] = 'Token logic';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_mode_options'] = ['and' => 'AND (all terms)', 'or' => 'OR (at least one term)', 'frontend' => 'Selectable in frontend'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_tokens'] = ['Max. tokens', 'Limit for multi-word search (0 = unlimited)'];
$GLOBALS['TL_LANG']['tl_module']['zotero_search_max_results'] = ['Max. results', 'Limit of search results (0 = unlimited)'];
$GLOBALS['TL_LANG']['tl_module']['search_config_legend'] = 'Search configuration';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_author_label'] = 'Author';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_author_all'] = '– All –';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_year_from'] = 'Year from';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_year_to'] = 'Year to';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_results'] = 'Search results';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_no_results'] = 'No publications found.';
$GLOBALS['TL_LANG']['tl_module']['zotero_search_token_limit_exceeded'] = 'Your search was limited to %s terms. Additional terms were ignored.';
