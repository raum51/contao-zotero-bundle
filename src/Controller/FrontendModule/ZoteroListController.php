<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zotero-Listen-Modul: Publikationsliste aus Zotero-Bibliothek.
 *
 * Liegt in src/Controller/FrontendModule/, da Contao Fragment-Controller dort erwartet.
 * Zeigt Items aus der konfigurierten Library (optional gefiltert nach Collections),
 * gerendert über das gewählte Zotero-Item-Template (cite_content, json_dl, fields).
 * Optional: Bei gesetztem zotero_reader_module und auto_item in der URL wird das
 * Lesemodul gerendert (News-Pattern).
 */
#[AsFrontendModule(
    type: 'zotero_list',
    category: 'zotero',
    template: 'frontend_module/zotero_list',
)]
final class ZoteroListController extends AbstractFrontendModuleController
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly Connection $connection,
        private readonly ZoteroSearchService $searchService,
        private readonly ZoteroLocaleLabelService $localeLabelService,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $readerModuleId = (int) ($model->zotero_reader_module ?? 0);
        $autoItem = Input::get('auto_item');

        // Reader auf derselben Seite: Lesemodul rendern statt Liste (News-Pattern)
        if ($readerModuleId > 0 && $autoItem !== null && $autoItem !== '') {
            $template->show_reader = true;
            $template->reader_module_id = $readerModuleId;

            return $template->getResponse();
        }

        $searchModuleId = (int) ($model->zotero_search_module ?? 0);
        $keywords = $request->query->getString('keywords');
        $zoteroAuthor = $request->query->get('zotero_author', '');
        $yearFrom = $request->query->get('zotero_year_from', '');
        $yearTo = $request->query->get('zotero_year_to', '');
        $hasSearchParams = $keywords !== '' || $zoteroAuthor !== '' || $yearFrom !== '' || $yearTo !== '';

        $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
        $collections = $this->parseCollectionIds($model->zotero_collections ?? '');
        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');

        if ($hasSearchParams && $searchModuleId > 0) {
            $searchModule = ModuleModel::findByPk($searchModuleId);
            if ($searchModule instanceof ModuleModel && $searchModule->type === 'zotero_search') {
                $searchLibraryIds = $this->parseLibraryIds($searchModule->zotero_libraries ?? '');
                $libraryIds = array_values(array_intersect($libraryIds, $searchLibraryIds));
                $searchFields = $this->parseSearchFields((string) ($searchModule->zotero_search_fields ?? 'title,tags,abstract'));
                if ($searchFields === []) {
                    $searchFields = ['title', 'tags', 'abstract'];
                }
                $tokenMode = (string) ($searchModule->zotero_search_token_mode ?? 'and');
                $maxTokens = (int) ($searchModule->zotero_search_max_tokens ?? 10) ?: 0;
                $maxResults = (int) ($searchModule->zotero_search_max_results ?? 0) ?: 0;

                $authorMemberId = $this->resolveAuthorToMemberId($zoteroAuthor);
                $yearFromInt = $this->parseYearParam($yearFrom);
                $yearToInt = $this->parseYearParam($yearTo);

                $page = max(1, (int) $request->query->get('page', 1));
                $offset = ($page - 1) * self::PER_PAGE;
                $limit = self::PER_PAGE;
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
                    $authorMemberId,
                    $yearFromInt,
                    $yearToInt,
                    $searchFields,
                    $tokenMode,
                    $maxTokens,
                    $fetchLimit,
                    $locale,
                    $offset
                    );
                }
                $hasMore = \count($rawItems) > $limit;
                $items = array_slice($rawItems, 0, $limit);

                $template->search_mode = true;
                $template->search_keywords = $keywords;
                $template->search_author = $zoteroAuthor;
                $template->search_year_from = $yearFrom;
                $template->search_year_to = $yearTo;
                $template->pagination = $this->buildPagination($page, $hasMore, $request);
            } else {
                $items = $this->fetchItems($libraryIds, $collections);
                $template->search_mode = false;
                $template->pagination = null;
            }
        } else {
            $items = $this->fetchItems($libraryIds, $collections);
            $template->search_mode = false;
            $template->pagination = null;
        }

        $pageMap = $this->getLibraryReaderPageMap($libraryIds, $model->zotero_reader_module ?? 0);

        foreach ($items as $i => $item) {
            $alias = $item['alias'] ?: (string) $item['id'];
            $page = $pageMap[$item['pid']] ?? null;
            $items[$i]['reader_url'] = $page instanceof PageModel
                ? $page->getFrontendUrl('/' . $alias)
                : null;
            if ($itemTemplate === 'json_dl') {
                $data = $item['data'] ?? [];
                $keys = \is_array($data) ? array_keys($data) : [];
                $items[$i]['field_labels'] = $this->localeLabelService->getItemFieldLabelsForKeys($keys, $this->resolveLocale($request));
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
     * Map libraryId => PageModel der Reader-Seite.
     * Bei zotero_reader_module: aktuelle Seite für alle. Sonst: library.jumpTo pro Library.
     * URLs mit getFrontendUrl('/' . $alias) erzeugen – damit Suffix (.html) und Routing korrekt.
     *
     * @param list<int> $libraryIds
     *
     * @return array<int, PageModel> libraryId => page
     */
    private function getLibraryReaderPageMap(array $libraryIds, int $readerModuleId): array
    {
        $map = [];
        if ($libraryIds === []) {
            return $map;
        }

        if ($readerModuleId > 0) {
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
     * @param list<int> $libraryIds
     * @param list<int> $collectionIds Leer = alle Collections
     *
     * @return list<array<string, mixed>>
     */
    private function fetchItems(array $libraryIds, array $collectionIds): array
    {
        if ($libraryIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('i.id', 'i.pid', 'i.alias', 'i.title', 'i.year', 'i.date', 'i.publication_title', 'i.item_type', 'i.cite_content', 'i.json_data')
            ->from('tl_zotero_item', 'i')
            ->where($qb->expr()->in('i.pid', ':pids'))
            ->andWhere('i.published = :published')
            ->setParameter('pids', $libraryIds, ArrayParameterType::INTEGER)
            ->setParameter('published', '1')
            ->orderBy('i.title', 'ASC');

        if ($collectionIds !== []) {
            $qb->innerJoin('i', 'tl_zotero_collection_item', 'ci', 'ci.item_id = i.id')
                ->andWhere($qb->expr()->in('ci.collection_id', ':coll_ids'))
                ->setParameter('coll_ids', $collectionIds, ArrayParameterType::INTEGER)
                ->groupBy('i.id');
        }

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
                'data' => \is_array($data) ? $data : [],
            ];
        }

        return $items;
    }

    /**
     * Ermittelt die Locale der aktuellen Seite (für Feld-Labels).
     * Request-Locale oder Root-Sprache der Seite, Fallback en.
     */
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

    /**
     * Parst einen Jahres-Parameter aus dem Suchformular.
     * Liefert null bei leerem, ungültigem oder 0-Wert (gültig: 1000–9999).
     */
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
     * @return array{current_page: int, next_page: int|null, prev_page: int|null, next_url: string|null, prev_url: string|null}
     */
    private function buildPagination(int $page, bool $hasMore, Request $request): array
    {
        $baseParams = $request->query->all();
        $nextUrl = null;
        $prevUrl = null;
        $prevPage = $page > 1 ? $page - 1 : null;
        $nextPage = $hasMore ? $page + 1 : null;

        if ($prevPage !== null) {
            $prevParams = array_merge($baseParams, ['page' => $prevPage]);
            $prevUrl = $request->getPathInfo() . '?' . http_build_query($prevParams);
        }
        if ($nextPage !== null) {
            $nextParams = array_merge($baseParams, ['page' => $nextPage]);
            $nextUrl = $request->getPathInfo() . '?' . http_build_query($nextParams);
        }

        return [
            'current_page' => $page,
            'next_page' => $nextPage,
            'prev_page' => $prevPage,
            'next_url' => $nextUrl,
            'prev_url' => $prevUrl,
        ];
    }
}
