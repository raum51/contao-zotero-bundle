<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\SitemapEvent;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Model\ZoteroItemModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fügt Zotero-Item-Detail-URLs zur Sitemap hinzu.
 *
 * Ermöglicht die Indexierung von Publikations-Detailseiten durch den Contao-Crawler,
 * sodass Zotero-Publikationen in der Website-weiten Suche erscheinen.
 *
 * **Library-basiert:** Pro Library Option „In Sitemap aufnehmen“ mit Filtern
 * (Collections, Item-Typen, Autoren). jumpTo-Seite muss veröffentlicht und nicht
 * protected sein. URL via ContentUrlGenerator (ZoteroItemContentUrlResolver).
 *
 * Liegt unter EventListener/, da Contao Event-Listener dort erwartet.
 */
#[AsEventListener(ContaoCoreEvents::SITEMAP)]
final class ZoteroSitemapListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContentUrlGenerator $contentUrlGenerator,
    ) {
    }

    public function __invoke(SitemapEvent $event): void
    {
        $rootPageIds = $event->getRootPageIds();
        if ($rootPageIds === []) {
            return;
        }

        $rootPageIdsMap = array_fill_keys($rootPageIds, true);
        $libraries = $this->connection->fetchAllAssociative(
            "SELECT id, jumpTo, sitemap_collections, sitemap_item_types, sitemap_authors
             FROM tl_zotero_library
             WHERE published = '1' AND include_in_sitemap = '1' AND jumpTo > 0"
        );

        foreach ($libraries as $lib) {
            $libraryId = (int) $lib['id'];
            $jumpTo = (int) $lib['jumpTo'];

            $page = PageModel::findPublishedById($jumpTo);
            if (!$page instanceof PageModel) {
                continue;
            }
            if (!empty($page->protected)) {
                continue;
            }
            $page->loadDetails();
            $rootId = (int) ($page->rootId ?? 0);
            if ($rootId <= 0 || !isset($rootPageIdsMap[$rootId])) {
                continue;
            }

            $collectionIds = $this->parseIds($lib['sitemap_collections'] ?? '');
            $itemTypes = $this->parseItemTypes($lib['sitemap_item_types'] ?? '');
            $authorMemberIds = $this->parseIds($lib['sitemap_authors'] ?? '');

            $itemIds = $this->fetchItemIdsForLibrary($libraryId, $collectionIds, $itemTypes, $authorMemberIds);

            foreach ($itemIds as $id) {
                $item = ZoteroItemModel::findByPk($id);
                if (!$item instanceof ZoteroItemModel) {
                    continue;
                }

                try {
                    $absoluteUrl = $this->contentUrlGenerator->generate(
                        $item,
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                    $event->addUrlToDefaultUrlSet($absoluteUrl);
                } catch (\Throwable) {
                    // Resolver gibt null zurück (z. B. jumpTo=0) – Item überspringen
                }
            }
        }
    }

    /**
     * @param list<int>    $collectionIds
     * @param list<string> $itemTypes
     * @param list<int>    $authorMemberIds
     *
     * @return list<int>
     */
    private function fetchItemIdsForLibrary(
        int $libraryId,
        array $collectionIds,
        array $itemTypes,
        array $authorMemberIds
    ): array {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('i.id')
            ->from('tl_zotero_item', 'i')
            ->where('i.pid = :pid')
            ->andWhere('i.published = :published')
            ->setParameter('pid', $libraryId)
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

        if ($authorMemberIds !== []) {
            $subQb = $this->connection->createQueryBuilder();
            $subQb->select('ic.item_id')
                ->from('tl_zotero_item_creator', 'ic')
                ->innerJoin('ic', 'tl_zotero_creator_map', 'cm', 'cm.id = ic.creator_map_id')
                ->where($subQb->expr()->in('cm.member_id', ':author_member_ids'));
            $qb->andWhere($qb->expr()->in('i.id', $subQb->getSQL()))
                ->setParameter('author_member_ids', $authorMemberIds, ArrayParameterType::INTEGER);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_values(array_map(static fn (array $r) => (int) $r['id'], $rows));
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $value): array
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
}
