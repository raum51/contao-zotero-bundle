<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_content']['title'] = ['Title', 'You can enter an optional title for the content element here.'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item'] = ['Zotero single element', 'One Zotero item (fixed or from URL)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode'] = ['Mode', 'Fixed item or item from URL (reader)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_mode_options'] = [
    'fixed' => 'Fixed item',
    'from_url' => 'Item from URL (reader)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_id'] = ['Zotero item', 'The item to display'];
$GLOBALS['TL_LANG']['tl_content']['zotero_libraries'] = ['Zotero libraries', 'Libraries to search for the item (mode "Item from URL")'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template_options'] = [
    'cite_content' => 'Citation (cite_content)',
    'json_dl' => 'Metadata as list (json_dl)',
    'fields' => 'Selected fields (fields)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_items'] = ['Zotero items (single/selected)', 'Display one or more items'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member_publications'] = ['Member publications', 'All publications linked to this Contao member'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection_publications'] = ['Collection publications', 'All items of the selected Zotero collection'];
$GLOBALS['TL_LANG']['tl_content']['zotero_member'] = ['Contao member', 'Member whose publications are displayed'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collection'] = ['Zotero collection', 'Collection whose items are displayed'];
$GLOBALS['TL_LANG']['tl_content']['zotero_template'] = ['Template', 'e.g. cite_content, json_dl, fields'];

// Zotero list (CE)
$GLOBALS['TL_LANG']['tl_content']['config_legend'] = 'List settings';
$GLOBALS['TL_LANG']['tl_content']['zotero_list'] = ['Zotero list', 'Publication list from Zotero library'];
$GLOBALS['TL_LANG']['tl_content']['zotero_collections'] = ['Collections', 'Optional: only show these collections (empty = all)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_item_types'] = ['Item types', 'Optional: only show these types (empty = all)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_author'] = ['Author', 'Optional: only show publications by this member'];
$GLOBALS['TL_LANG']['tl_content']['zotero_reader_element'] = ['Reader element', 'Zotero single element (from URL) for detail view on same page'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_element'] = ['Search element', 'Zotero search CE for libraries and search config in search mode'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_module'] = ['Search module', 'Alternatively: Zotero search module (if no search CE is used)'];

// Zotero search (CE)
$GLOBALS['TL_LANG']['tl_content']['zotero_search'] = ['Zotero Search/Filter', 'Search form and filter for publications'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_enabled'] = ['Enable search field', 'Unchecked: Filter only (author, year, item type), no search field'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_sort_by_weight'] = ['Sort by relevance', 'When searching: sort by weight (otherwise use list sort/group)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_title'] = ['Weight title', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_creators'] = ['Weight creators', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_tags'] = ['Weight tags', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_publication_title'] = ['Weight publication title', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_year'] = ['Weight year', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_abstract'] = ['Weight abstract', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_weight_zotero_key'] = ['Weight Zotero key', '0 = do not search'];
$GLOBALS['TL_LANG']['tl_content']['search_config_legend'] = 'Search configuration';
$GLOBALS['TL_LANG']['tl_content']['zotero_list_page'] = ['Target page list element', 'Page with Zotero list for search results'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_author'] = ['Show author filter', 'Author dropdown in search form'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_year'] = ['Show year filter', 'Year from/to fields in search form'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_show_item_type'] = ['Show item type filter', 'Item type dropdown in search form'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_fields'] = ['Searchable fields', 'Order = priority (e.g. title,tags,abstract)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode'] = ['Token logic', 'AND: all terms; OR: at least one; Frontend: user selects'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_token_mode_options'] = ['and' => 'AND (all terms)', 'or' => 'OR (at least one)', 'frontend' => 'Selectable in frontend'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_tokens'] = ['Max. token count', 'Limit for multi-word search (0 = unlimited)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_search_max_results'] = ['Max. results', 'Limit for search results (0 = unlimited)'];
$GLOBALS['TL_LANG']['tl_content']['numberOfItems'] = ['Number of items', 'Number of items to display (0 = all)'];
$GLOBALS['TL_LANG']['tl_content']['perPage'] = ['Items per page', 'Number per page for pagination (0 = no pagination)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_order'] = ['Sort order', 'Order of entries'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_order_options'] = [
    'order_author_date' => 'Author (first), publication date',
    'order_year_author' => 'Year, author (first)',
    'order_title' => 'Title',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date'] = ['Date/year sort direction', 'Applies to sort and grouping'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_sort_direction_date_options'] = [
    'asc' => 'Ascending (oldest first)',
    'desc' => 'Descending (newest first)',
];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_group'] = ['Grouping', 'Display list grouped (empty = no grouping)'];
$GLOBALS['TL_LANG']['tl_content']['zotero_list_group_options'] = [
    'library' => 'Library',
    'collection' => 'Collection',
    'item_type' => 'Item type',
    'year' => 'Year',
];
