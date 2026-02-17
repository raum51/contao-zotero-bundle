<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Routing;

use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Model\ZoteroItemModel;

/**
 * ContentUrlResolver für ZoteroItemModel.
 *
 * Ermöglicht die Erzeugung von Frontend-URLs zu Zotero-Detailseiten über den
 * Contao ContentUrlGenerator. Die Zielseite wird aus der Library (item->pid)
 * über deren jumpTo ermittelt; bei jumpTo=0 existiert keine Auflösung.
 *
 * Liegt unter Routing/, da Contao Content-URL-Resolver dort typischerweise
 * angesiedelt sind (analog NewsBundle/Routing/NewsResolver).
 */
final class ZoteroItemContentUrlResolver implements ContentUrlResolverInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function resolve(object $content): ContentUrlResult|null
    {
        if (!$content instanceof ZoteroItemModel) {
            return null;
        }

        $jumpTo = (int) $this->connection->fetchOne(
            'SELECT jumpTo FROM tl_zotero_library WHERE id = ?',
            [(int) $content->pid]
        );

        if ($jumpTo <= 0) {
            return null;
        }

        $page = PageModel::findPublishedById($jumpTo);
        if (!$page instanceof PageModel) {
            return null;
        }

        return ContentUrlResult::resolve($page);
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof ZoteroItemModel) {
            return [];
        }

        $suffix = ($content->alias ?? '') !== '' ? $content->alias : (string) $content->id;

        return ['parameters' => '/' . $suffix];
    }
}
