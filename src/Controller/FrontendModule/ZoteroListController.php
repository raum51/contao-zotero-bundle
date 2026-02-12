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
    public function __construct(
        private readonly Connection $connection,
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

        $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
        $collections = $this->parseCollectionIds($model->zotero_collections ?? '');
        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');

        $items = $this->fetchItems($libraryIds, $collections);
        $pageMap = $this->getLibraryReaderPageMap($libraryIds, $model->zotero_reader_module ?? 0);

        foreach ($items as $i => $item) {
            $alias = $item['alias'] ?: (string) $item['id'];
            $page = $pageMap[$item['pid']] ?? null;
            $items[$i]['reader_url'] = $page instanceof PageModel
                ? $page->getFrontendUrl('/' . $alias)
                : null;
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
}
