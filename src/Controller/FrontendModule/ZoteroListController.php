<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
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
 */
#[AsFrontendModule(
    type: 'zotero_list',
    category: 'miscellaneous',
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
        $libraryId = (int) $model->zotero_library;
        $collections = $this->parseCollectionIds($model->zotero_collections ?? '');
        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');

        $items = $this->fetchItems($libraryId, $collections);

        $template->items = $items;
        $template->item_template = $itemTemplate;

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? '',
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
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
     * @param list<int> $collectionIds Leer = alle Collections
     *
     * @return list<array<string, mixed>>
     */
    private function fetchItems(int $libraryId, array $collectionIds): array
    {
        if ($libraryId <= 0) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('i.id', 'i.alias', 'i.title', 'i.year', 'i.date', 'i.publication_title', 'i.item_type', 'i.cite_content', 'i.json_data')
            ->from('tl_zotero_item', 'i')
            ->where('i.pid = :pid')
            ->andWhere('i.published = :published')
            ->setParameter('pid', $libraryId)
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
