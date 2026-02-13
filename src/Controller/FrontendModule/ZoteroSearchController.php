<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly ZoteroLocaleLabelService $localeLabelService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $listPageId = (int) ($model->zotero_list_page ?? 0);
        $showAuthor = (bool) ($model->zotero_search_show_author ?? true);
        $showYear = (bool) ($model->zotero_search_show_year ?? true);
        $showItemType = (bool) ($model->zotero_search_show_item_type ?? false);

        $page = $listPageId > 0 ? PageModel::findPublishedById($listPageId) : null;
        $formAction = $page instanceof PageModel ? $page->getFrontendUrl() : '';

        $locale = $request->getLocale() ?: 'en';
        $template->form_action = $formAction;
        $template->show_author = $showAuthor;
        $template->show_year = $showYear;
        $template->show_item_type = $showItemType;
        $template->authors = $showAuthor ? $this->searchService->getMembersWithCreatorMapping() : [];
        $template->item_types = $showItemType ? $this->localeLabelService->getAllItemTypeLabels($locale) : [];

        $template->label_keywords = $this->translator->trans('MSC.keywords', [], 'contao_default');
        $template->label_author = $this->translator->trans('tl_module.zotero_search_author_label', [], 'contao_tl_module');
        $template->label_author_all = $this->translator->trans('tl_module.zotero_search_author_all', [], 'contao_tl_module');
        $template->label_year_from = $this->translator->trans('tl_module.zotero_search_year_from', [], 'contao_tl_module');
        $template->label_year_to = $this->translator->trans('tl_module.zotero_search_year_to', [], 'contao_tl_module');
        $template->label_item_type = $this->translator->trans('tl_module.zotero_search_item_type_label', [], 'contao_tl_module');
        $template->label_item_type_all = $this->translator->trans('tl_module.zotero_search_item_type_all', [], 'contao_tl_module');
        $template->label_search = $this->translator->trans('MSC.search', [], 'contao_default');

        $template->keywords = $request->query->getString('keywords');
        $template->zotero_author = $request->query->get('zotero_author', '');
        $template->zotero_year_from = $request->query->get('zotero_year_from', '');
        $template->zotero_year_to = $request->query->get('zotero_year_to', '');
        $template->zotero_item_type = $request->query->get('zotero_item_type', '');

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? '',
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
    }
}
