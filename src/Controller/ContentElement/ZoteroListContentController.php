<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\ContentElement;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zotero-Listen-Inhaltselement: Publikationsliste aus Zotero-Bibliothek.
 *
 * Analog zum Listen-Modul (zotero_list), nutzt aber zotero_reader_element (CE)
 * statt zotero_reader_module und unterstützt zotero_author als Listen-Filter.
 *
 * Liegt unter Controller/ContentElement/, da Contao CE-Controller dort erwartet.
 */
#[AsContentElement(
    type: 'zotero_list',
    category: 'zotero',
    template: 'content_element/zotero_list',
)]
final class ZoteroListContentController extends AbstractContentElementController
{
    private const DEFAULT_PER_PAGE = 12;

    public function __construct(
        private readonly Connection $connection,
        private readonly ZoteroSearchService $searchService,
        private readonly ZoteroLocaleLabelService $localeLabelService,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $readerElementId = (int) ($model->zotero_reader_element ?? 0);
        $autoItem = Input::get('auto_item');

        // Reader auf derselben Seite: Reader-CE rendern statt Liste (News-Pattern)
        if ($readerElementId > 0 && $autoItem !== null && $autoItem !== '') {
            $template->show_reader = true;
            $template->reader_element_id = $readerElementId;

            return $template->getResponse();
        }

        $searchElementId = (int) ($model->zotero_search_element ?? 0);
        $searchModuleId = (int) ($model->zotero_search_module ?? 0);
        $keywords = $request->query->getString('keywords');
        $zoteroAuthor = $request->query->get('zotero_author', '');
        $yearFrom = $request->query->get('zotero_year_from', '');
        $yearTo = $request->query->get('zotero_year_to', '');
        $zoteroItemType = $request->query->get('zotero_item_type', '');
        $hasSearchParams = $keywords !== '' || $zoteroAuthor !== '' || $yearFrom !== '' || $yearTo !== '' || $zoteroItemType !== '';

        $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
        $collections = $this->parseCollectionIds($model->zotero_collections ?? '');
        $itemTypes = $this->parseItemTypes($model->zotero_item_types ?? '');
        $authorMemberId = (int) ($model->zotero_author ?? 0) ?: null;
        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');
        $requireCiteContent = ($itemTemplate === 'cite_content');
        $numberOfItems = (int) ($model->numberOfItems ?? 0);
        $perPageRaw = $model->perPage ?? null;
        $perPage = ($perPageRaw !== null && $perPageRaw !== '')
            ? (int) $perPageRaw
            : self::DEFAULT_PER_PAGE;
        $sortOrder = (string) ($model->zotero_list_order ?? 'order_title');
        $sortDirectionDate = (string) ($model->zotero_list_sort_direction_date ?? 'desc');
        $groupBy = (string) ($model->zotero_list_group ?? '');

        $searchSource = $this->resolveSearchSource($searchElementId, $searchModuleId);
        if ($hasSearchParams && $searchSource !== null) {
            $searchLibraryIds = $this->parseLibraryIds($searchSource['zotero_libraries'] ?? '');
            $libraryIds = array_values(array_intersect($libraryIds, $searchLibraryIds));
            $effectiveItemTypes = $this->resolveEffectiveItemTypesForSearch($itemTypes, $zoteroItemType);
            $searchFields = $this->parseSearchFields((string) ($searchSource['zotero_search_fields'] ?? 'title,tags,abstract'));
            if ($searchFields === []) {
                $searchFields = ['title', 'tags', 'abstract'];
            }
            $tokenMode = (string) ($searchSource['zotero_search_token_mode'] ?? 'and');
            $maxTokens = (int) ($searchSource['zotero_search_max_tokens'] ?? 10) ?: 0;
            $maxResults = (int) ($searchSource['zotero_search_max_results'] ?? 0) ?: 0;

            $searchAuthorMemberId = $this->resolveAuthorToMemberId($zoteroAuthor);
            $yearFromInt = $this->parseYearParam($yearFrom);
            $yearToInt = $this->parseYearParam($yearTo);

            $page = max(1, (int) $request->query->get('page', 1));
            $limit = $perPage > 0 ? $perPage : 9999;
            $offset = $perPage > 0 ? ($page - 1) * $perPage : 0;
            if ($maxResults > 0) {
                $remaining = $maxResults - $offset;
                $limit = $remaining <= 0 ? 0 : min($limit, $remaining);
            }

            $locale = $request->getLocale();
            $rawItems = [];
            if ($limit > 0) {
                $fetchLimit = $limit + 1;
                $rawItems = $this->searchService->search(
                        $libraryIds,
                        $keywords,
                        $searchAuthorMemberId,
                        $yearFromInt,
                        $yearToInt,
                        $effectiveItemTypes,
                        $searchFields,
                        $tokenMode,
                        $maxTokens,
                        $fetchLimit,
                        $locale,
                        $offset,
                    $requireCiteContent
                );
            }
            $hasMore = \count($rawItems) > $limit;
            $items = array_slice($rawItems, 0, $limit);
            $totalForSearch = $offset + \count($items) + ($hasMore ? ($perPage > 0 ? $perPage : $limit) : 0);

            $template->search_mode = true;
            $template->items = $items;
            $template->search_keywords = $keywords;
            $template->search_author = $zoteroAuthor;
            $template->search_year_from = $yearFrom;
            $template->search_year_to = $yearTo;
            $template->search_item_type = $zoteroItemType;
            $template->total = $totalForSearch;
            $template->groups = null;
            $template->pagination = $this->buildPaginationHtml($totalForSearch, $perPage, $page, (int) $model->id, $request);
        } else {
            [$items, $total, $groups] = $this->fetchItemsWithMeta($libraryIds, $collections, $itemTypes, $sortOrder, $sortDirectionDate, $groupBy, $numberOfItems, $perPage, $request, $requireCiteContent, $authorMemberId);
            $template->search_mode = false;
            $template->items = $items;
            $template->total = $total;
            $template->groups = $groups;
            $template->pagination = $this->buildPaginationHtml($total, $perPage, max(1, (int) $request->query->get('page', 1)), (int) $model->id, $request);
        }

        $pageMap = $this->getLibraryReaderPageMap($libraryIds, $readerElementId > 0);
        $locale = $this->resolveLocale($request);

        foreach ($items as $i => $entry) {
            if (isset($entry['item'])) {
                $pid = (int) $entry['item']['pid'];
                $page = $pageMap[$pid] ?? null;
                $items[$i]['item']['reader_url'] = $page instanceof PageModel
                    ? $page->getFrontendUrl('/' . ($entry['item']['alias'] ?: (string) $entry['item']['id']))
                    : null;
                if ($itemTemplate === 'json_dl') {
                    $data = $entry['item']['data'] ?? [];
                    $keys = \is_array($data) ? array_keys($data) : [];
                    $items[$i]['item']['field_labels'] = $this->localeLabelService->getItemFieldLabelsForKeys($keys, $locale);
                }
            } else {
                $page = $pageMap[$entry['pid']] ?? null;
                $items[$i]['reader_url'] = $page instanceof PageModel
                    ? $page->getFrontendUrl('/' . ($entry['alias'] ?: (string) $entry['id']))
                    : null;
                if ($itemTemplate === 'json_dl') {
                    $data = $entry['data'] ?? [];
                    $keys = \is_array($data) ? array_keys($data) : [];
                    $items[$i]['field_labels'] = $this->localeLabelService->getItemFieldLabelsForKeys($keys, $locale);
                }
            }
        }

        $template->items = $items;
        $template->item_template = $itemTemplate;
        $template->show_reader = false;

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? '',
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
    }

    /**
     * Map libraryId => PageModel. Bei Reader-CE: aktuelle Seite; sonst library.jumpTo.
     *
     * @param list<int> $libraryIds
     *
     * @return array<int, PageModel>
     */
    private function getLibraryReaderPageMap(array $libraryIds, bool $hasReaderElement): array
    {
        $map = [];
        if ($libraryIds === []) {
            return $map;
        }

        if ($hasReaderElement) {
            $page = $this->getPageModel();
            if ($page instanceof PageModel) {
                foreach ($libraryIds as $id) {
                    $map[$id] = $page;
                }
            }

            return $map;
        }

        $placeholders = implode(',', array_fill(0, \count($libraryIds), '?'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, jumpTo FROM tl_zotero_library WHERE id IN (' . $placeholders . ')',
            $libraryIds
        );
        foreach ($rows as $row) {
            $jumpTo = (int) ($row['jumpTo'] ?? 0);
            if ($jumpTo > 0) {
                $page = PageModel::findPublishedById($jumpTo);
                if ($page instanceof PageModel) {
                    $map[(int) $row['id']] = $page;
                }
            }
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function parseLibraryIds(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $ids = unserialize($value, ['allowed_classes' => false]);
        if (!\is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($ids, 'is_numeric')));
    }

    /**
     * @return list<int>
     */
    private function parseCollectionIds(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $ids = unserialize($value, ['allowed_classes' => false]);
        if (!\is_array($ids)) {
            return [];
        }

        return array_map('intval', array_filter($ids, 'is_numeric'));
    }

    /**
     * @return list<string>
     */
    private function parseItemTypes(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $ids = unserialize($value, ['allowed_classes' => false]);
        if (!\is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, static fn ($v) => \is_string($v) && $v !== ''));
    }

    /**
     * @param list<string> $itemTypes
     */
    private function resolveEffectiveItemTypesForSearch(array $itemTypes, string $zoteroItemType): ?array
    {
        $formType = $zoteroItemType !== '' ? $zoteroItemType : null;
        if ($itemTypes === []) {
            return $formType !== null ? [$formType] : null;
        }
        if ($formType !== null) {
            return \in_array($formType, $itemTypes, true) ? [$formType] : [];
        }

        return $itemTypes;
    }

    /**
     * @return list<string>
     */
    private function parseSearchFields(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $allowed = ['title', 'tags', 'abstract'];

        return array_values(array_filter($parts, static fn (string $p) => $p !== '' && \in_array($p, $allowed, true)));
    }

    private function resolveAuthorToMemberId(string $value): ?int
    {
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function parseYearParam(string $value): ?int
    {
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $year = (int) $value;
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $year;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: list<array{key: string, label: string}>|null}
     */
    private function fetchItemsWithMeta(
        array $libraryIds,
        array $collectionIds,
        array $itemTypes,
        string $sortOrder,
        string $sortDirectionDate,
        string $groupBy,
        int $numberOfItems,
        int $perPage,
        Request $request,
        bool $requireCiteContent,
        ?int $authorMemberId
    ): array {
        $baseItems = $this->fetchItems($libraryIds, $collectionIds, $itemTypes, $sortOrder, $sortDirectionDate, $numberOfItems > 0 ? $numberOfItems : null, $requireCiteContent, $authorMemberId);

        if ($groupBy !== '') {
            return $this->applyGroupingAndPagination($baseItems, $groupBy, $sortOrder, $sortDirectionDate, $perPage, $numberOfItems, $request);
        }

        $total = \count($baseItems);
        $page = max(1, (int) $request->query->get('page', 1));
        $items = $perPage <= 0 ? $baseItems : array_slice($baseItems, ($page - 1) * $perPage, $perPage);

        return [$items, $total, null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchItems(array $libraryIds, array $collectionIds, array $itemTypes, string $sortOrder, string $sortDirectionDate, ?int $limit, bool $requireCiteContent, ?int $authorMemberId): array
    {
        if ($libraryIds === []) {
            return [];
        }

        $firstAuthorSub = '(SELECT CONCAT(COALESCE(cm.zotero_lastname,\'\'), \' \', COALESCE(cm.zotero_firstname,\'\'))
            FROM tl_zotero_item_creator ic
            JOIN tl_zotero_creator_map cm ON cm.id = ic.creator_map_id
            WHERE ic.item_id = i.id
            ORDER BY ic.sorting ASC, ic.id ASC LIMIT 1)';

        $selectCols = [
            'i.id', 'i.pid', 'i.alias', 'i.title', 'i.year', 'i.date', 'i.publication_title',
            'i.item_type', 'i.cite_content', 'i.json_data',
        ];
        $selectCols[] = $collectionIds !== [] ? 'MAX(' . $firstAuthorSub . ') AS first_author_sort' : $firstAuthorSub . ' AS first_author_sort';

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...$selectCols)
            ->from('tl_zotero_item', 'i')
            ->where($qb->expr()->in('i.pid', ':pids'))
            ->andWhere('i.published = :published')
            ->setParameter('pids', $libraryIds, ArrayParameterType::INTEGER)
            ->setParameter('published', '1');

        if ($collectionIds !== []) {
            $qb->innerJoin('i', 'tl_zotero_collection_item', 'ci', 'ci.item_id = i.id')
                ->andWhere($qb->expr()->in('ci.collection_id', ':coll_ids'))
                ->setParameter('coll_ids', $collectionIds, ArrayParameterType::INTEGER)
                ->groupBy('i.id');
        }

        if ($itemTypes !== []) {
            $qb->andWhere($qb->expr()->in('i.item_type', ':item_types'))
                ->setParameter('item_types', $itemTypes, ArrayParameterType::STRING);
        }

        if ($authorMemberId !== null) {
            $subQb = $this->connection->createQueryBuilder();
            $subQb->select('ic.item_id')
                ->from('tl_zotero_item_creator', 'ic')
                ->innerJoin('ic', 'tl_zotero_creator_map', 'cm', 'cm.id = ic.creator_map_id')
                ->where('cm.member_id = :author_member_id');
            $qb->andWhere($qb->expr()->in('i.id', $subQb->getSQL()))
                ->setParameter('author_member_id', $authorMemberId);
        }

        if ($requireCiteContent) {
            $qb->andWhere('i.cite_content IS NOT NULL')
                ->andWhere("i.cite_content != ''");
        }

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        $authorSub = '(SELECT COALESCE(cm.zotero_lastname,\'\') FROM tl_zotero_item_creator ic JOIN tl_zotero_creator_map cm ON cm.id = ic.creator_map_id WHERE ic.item_id = i.id ORDER BY ic.sorting ASC, ic.id ASC LIMIT 1)';
        $dateDir = strtoupper($sortDirectionDate) === 'ASC' ? 'ASC' : 'DESC';
        match ($sortOrder) {
            'order_author_date' => $qb->orderBy($authorSub, 'ASC')->addOrderBy('i.date', $dateDir),
            'order_year_author' => $qb->orderBy('i.year', $dateDir)->addOrderBy($authorSub, 'ASC'),
            default => $qb->orderBy('i.title', 'ASC'),
        };

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $items = [];
        foreach ($rows as $row) {
            $jsonData = $row['json_data'] ?? '{}';
            $data = json_decode($jsonData, true);
            $items[] = [
                'id' => (int) $row['id'],
                'pid' => (int) $row['pid'],
                'alias' => $row['alias'] ?? '',
                'title' => $row['title'] ?? '',
                'year' => $row['year'] ?? '',
                'date' => $row['date'] ?? '',
                'publication_title' => $row['publication_title'] ?? '',
                'item_type' => $row['item_type'] ?? '',
                'cite_content' => $row['cite_content'] ?? '',
                'first_author_sort' => $row['first_author_sort'] ?? '',
                'data' => \is_array($data) ? $data : [],
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $baseItems
     *
     * @return array{0: list<array<string, mixed>>, 1: int, 2: list<array{key: string, label: string}>}
     */
    private function applyGroupingAndPagination(array $baseItems, string $groupBy, string $sortOrder, string $sortDirectionDate, int $perPage, int $numberOfItems, Request $request): array
    {
        $rows = [];
        $libraryTitles = [];
        $collectionTitles = [];
        $itemTypeLabels = [];
        $locale = $this->resolveLocale($request);

        foreach ($baseItems as $item) {
            if ($groupBy === 'library') {
                $pid = (int) $item['pid'];
                $key = 'lib_' . $pid;
                $label = $libraryTitles[$pid] ??= $this->getLibraryTitle($pid) ?: (string) $pid;
                $rows[] = ['group_key' => $key, 'group_label' => $label, 'item' => $item];
            } elseif ($groupBy === 'collection') {
                $this->expandItemForCollections($item, $rows, $collectionTitles);
            } elseif ($groupBy === 'item_type') {
                $type = $item['item_type'] ?? '';
                $label = $itemTypeLabels[$type] ??= $this->localeLabelService->getItemTypeLabel($type, $locale) ?: $type;
                $rows[] = ['group_key' => 'type_' . $type, 'group_label' => $label, 'item' => $item];
            } elseif ($groupBy === 'year') {
                $year = $item['year'] ?? '';
                $rows[] = ['group_key' => 'year_' . $year, 'group_label' => $year ?: '–', 'item' => $item];
            } else {
                $rows[] = $item;
            }
        }

        $this->sortGroupedRows($rows, $sortOrder, $sortDirectionDate, $groupBy);
        $total = \count($rows);
        $pageRows = $perPage <= 0 ? $rows : array_slice($rows, (max(1, (int) $request->query->get('page', 1)) - 1) * $perPage, $perPage);

        $groupOptions = [];
        $seen = [];
        foreach ($rows as $r) {
            $key = $r['group_key'] ?? '';
            $label = $r['group_label'] ?? '';
            if ($key !== '' && !isset($seen[$key])) {
                $seen[$key] = true;
                $groupOptions[] = ['key' => $key, 'label' => $label];
            }
        }

        return [$pageRows, $total, $groupOptions];
    }

    private function expandItemForCollections(array $item, array &$rows, array &$collectionTitles): void
    {
        $itemId = (int) $item['id'];
        $collIds = $this->connection->fetchFirstColumn(
            'SELECT collection_id FROM tl_zotero_collection_item WHERE item_id = ?',
            [$itemId]
        );
        if ($collIds === []) {
            $rows[] = ['group_key' => 'coll_none', 'group_label' => '–', 'item' => $item];
            return;
        }
        foreach ($collIds as $cid) {
            $cid = (int) $cid;
            $label = $collectionTitles[$cid] ??= $this->getCollectionTitle($cid) ?: (string) $cid;
            $rows[] = ['group_key' => 'coll_' . $cid, 'group_label' => $label, 'item' => $item];
        }
    }

    private function sortGroupedRows(array &$rows, string $sortOrder, string $sortDirectionDate, string $groupBy): void
    {
        $getItem = static fn ($r) => $r['item'] ?? $r;
        $getAuthor = static fn ($r) => ($getItem($r)['first_author_sort'] ?? '');
        $getDate = static fn ($r) => ($getItem($r)['date'] ?? '');
        $getYear = static fn ($r) => ($getItem($r)['year'] ?? '');
        $dateDesc = strtoupper($sortDirectionDate) !== 'ASC';

        usort($rows, static function ($a, $b) use ($sortOrder, $dateDesc, $groupBy, $getItem, $getAuthor, $getDate, $getYear) {
            $cmp = 0;
            if (isset($a['group_key'], $b['group_key']) && $a['group_key'] !== $b['group_key']) {
                if ($groupBy === 'year') {
                    $yearA = (int) preg_replace('/^year_/', '', $a['group_key'], 1);
                    $yearB = (int) preg_replace('/^year_/', '', $b['group_key'], 1);
                    $cmp = $yearA <=> $yearB;
                    if ($dateDesc && $cmp !== 0) {
                        $cmp = -$cmp;
                    }
                } else {
                    $cmp = strcmp($a['group_label'] ?? $a['group_key'], $b['group_label'] ?? $b['group_key']);
                }
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            $dateCmp = $dateDesc ? strcmp($getDate($b), $getDate($a)) : strcmp($getDate($a), $getDate($b));
            $yearCmp = $dateDesc ? strcmp($getYear($b), $getYear($a)) : strcmp($getYear($a), $getYear($b));
            return match ($sortOrder) {
                'order_author_date' => $cmp ?: (strcmp($getAuthor($a), $getAuthor($b)) ?: $dateCmp),
                'order_year_author' => $cmp ?: ($yearCmp ?: strcmp($getAuthor($a), $getAuthor($b))),
                default => $cmp ?: strcmp($getItem($a)['title'] ?? '', $getItem($b)['title'] ?? ''),
            };
        });
    }

    private function getLibraryTitle(int $id): string
    {
        $row = $this->connection->fetchOne('SELECT title FROM tl_zotero_library WHERE id = ?', [$id]);
        return \is_string($row) ? $row : '';
    }

    private function getCollectionTitle(int $id): string
    {
        $row = $this->connection->fetchOne('SELECT title FROM tl_zotero_collection WHERE id = ?', [$id]);
        return \is_string($row) ? $row : '';
    }

    private function buildPaginationHtml(int $total, int $perPage, int $page, int $contentId, Request $request): ?string
    {
        if ($perPage <= 0 || $total <= $perPage) {
            return null;
        }
        $maxPage = (int) ceil($total / $perPage);
        if ($page < 1 || $page > $maxPage) {
            return null;
        }
        $config = $this->getContaoAdapter(Config::class);
        $param = 'page';
        $pagination = new Pagination($total, $perPage, $config->get('maxPaginationLinks'), $param);
        return $pagination->generate("\n  ");
    }

    /**
     * Liefert Such-Konfiguration aus Such-CE oder Such-Modul.
     * Priorität: Such-CE, falls gesetzt; sonst Such-Modul.
     *
     * @return array<string, mixed>|null
     */
    private function resolveSearchSource(int $searchElementId, int $searchModuleId): ?array
    {
        if ($searchElementId > 0) {
            $ce = ContentModel::findByPk($searchElementId);
            if ($ce instanceof ContentModel && $ce->type === 'zotero_search') {
                return [
                    'zotero_libraries' => $ce->zotero_libraries ?? '',
                    'zotero_search_fields' => $ce->zotero_search_fields ?? 'title,tags,abstract',
                    'zotero_search_token_mode' => $ce->zotero_search_token_mode ?? 'and',
                    'zotero_search_max_tokens' => $ce->zotero_search_max_tokens ?? 10,
                    'zotero_search_max_results' => $ce->zotero_search_max_results ?? 0,
                ];
            }
        }
        if ($searchModuleId > 0) {
            $mod = ModuleModel::findByPk($searchModuleId);
            if ($mod instanceof ModuleModel && $mod->type === 'zotero_search') {
                return [
                    'zotero_libraries' => $mod->zotero_libraries ?? '',
                    'zotero_search_fields' => $mod->zotero_search_fields ?? 'title,tags,abstract',
                    'zotero_search_token_mode' => $mod->zotero_search_token_mode ?? 'and',
                    'zotero_search_max_tokens' => $mod->zotero_search_max_tokens ?? 10,
                    'zotero_search_max_results' => $mod->zotero_search_max_results ?? 0,
                ];
            }
        }

        return null;
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->getLocale();
        if ($locale !== '' && $locale !== null) {
            return (string) $locale;
        }
        $page = $this->getPageModel();
        if ($page instanceof PageModel) {
            $page->loadDetails();
            if (!empty($page->rootId)) {
                $root = PageModel::findByPk($page->rootId);
                if ($root instanceof PageModel && $root->language !== '') {
                    return (string) $root->language;
                }
            }
            if ($page->language !== '') {
                return (string) $page->language;
            }
        }
        return 'en';
    }
}
