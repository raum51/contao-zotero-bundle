<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Raum51\ContaoZoteroBundle\Model\ZoteroItemModel;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zotero-Lese-Modul: Detailansicht eines Zotero-Items.
 *
 * Liest auto_item aus der URL (Contao Legacy Parameters), lädt das Item per Alias/ID
 * und Library, rendert es mit dem konfigurierten Template.
 * Ohne auto_item: leere Ausgabe (kombiniert mit Listenmodul auf gleicher Seite).
 */
#[AsFrontendModule(
    type: 'zotero_reader',
    category: 'zotero',
    template: 'frontend_module/zotero_reader',
)]
final class ZoteroReaderController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly ZoteroLocaleLabelService $localeLabelService,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $autoItem = Input::get('auto_item');

        // Kein Item in der URL – leer (Listenmodul zeigt Liste)
        if ($autoItem === null || $autoItem === '') {
            return new Response('', Response::HTTP_OK);
        }

        $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
        if ($libraryIds === []) {
            return new Response('', Response::HTTP_OK);
        }

        $item = ZoteroItemModel::findPublishedByParentAndIdOrAliasInLibraries($autoItem, $libraryIds);
        if ($item === null) {
            throw new PageNotFoundException('Zotero-Item nicht gefunden: ' . $autoItem);
        }

        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');

        $itemArray = $this->itemToArray($item);
        if ($itemTemplate === 'json_dl') {
            $data = $itemArray['data'] ?? [];
            $keys = \is_array($data) ? array_keys($data) : [];
            $itemArray['field_labels'] = $this->localeLabelService->getItemFieldLabelsForKeys($keys, $this->resolveLocale($request));
        }
        $template->item = $itemArray;
        $template->item_template = $itemTemplate;

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? $item->title,
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
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
     * @return array<string, mixed>
     */
    private function itemToArray(ZoteroItemModel $item): array
    {
        $jsonData = $item->json_data ?? '{}';
        $data = json_decode($jsonData, true);

        return [
            'id' => (int) $item->id,
            'alias' => $item->alias ?? '',
            'title' => $item->title ?? '',
            'year' => $item->year ?? '',
            'date' => $item->date ?? '',
            'publication_title' => $item->publication_title ?? '',
            'item_type' => $item->item_type ?? '',
            'cite_content' => $item->cite_content ?? '',
            'bib_content' => $item->bib_content ?? '',
            'data' => \is_array($data) ? $data : [],
        ];
    }
}
