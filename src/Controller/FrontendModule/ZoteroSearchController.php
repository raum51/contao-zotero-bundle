<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Raum51\ContaoZoteroBundle\Service\ZoteroSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zotero-Such-Modul: Suchformular für Publikationen.
 *
 * Liegt in src/Controller/FrontendModule/, da Contao Fragment-Controller dort erwartet.
 * Rendert nur das Formular; leitet per GET auf zotero_list_page weiter.
 * Entspricht dem Contao-Suchmodul-Pattern (Formular → Weiterleitung → Listen-Modul zeigt Ergebnisse).
 */
#[AsFrontendModule(
    type: 'zotero_search',
    category: 'zotero',
    template: 'frontend_module/zotero_search',
)]
final class ZoteroSearchController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly ZoteroSearchService $searchService,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $listPageId = (int) ($model->zotero_list_page ?? 0);
        $showAuthor = (bool) ($model->zotero_search_show_author ?? true);
        $showYear = (bool) ($model->zotero_search_show_year ?? true);

        $page = $listPageId > 0 ? PageModel::findPublishedById($listPageId) : null;
        $formAction = $page instanceof PageModel ? $page->getFrontendUrl() : '';

        $template->form_action = $formAction;
        $template->show_author = $showAuthor;
        $template->show_year = $showYear;
        $template->authors = $showAuthor ? $this->searchService->getMembersWithCreatorMapping() : [];

        $template->keywords = $request->query->getString('keywords');
        $template->zotero_author = $request->query->get('zotero_author', '');
        $template->zotero_year_from = $request->query->get('zotero_year_from', '');
        $template->zotero_year_to = $request->query->get('zotero_year_to', '');

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? '',
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
    }
}
