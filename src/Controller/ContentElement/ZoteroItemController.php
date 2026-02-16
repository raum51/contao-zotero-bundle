<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Model\ZoteroItemModel;
use Raum51\ContaoZoteroBundle\Service\ZoteroAttachmentResolver;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zotero-Einzelelement: Anzeige eines einzelnen Zotero-Items.
 *
 * Zwei Modi:
 * - fixed: Ein fest gewähltes Item (z. B. in Artikeln, Sidebar)
 * - from_url: Item aus auto_item in der URL (Reader-Modus, News-Pattern)
 *
 * Liegt unter Controller/ContentElement/, da Contao CE-Controller dort erwartet.
 */
#[AsContentElement(
    type: 'zotero_item',
    category: 'zotero',
    template: 'content_element/zotero_item',
)]
final class ZoteroItemController extends AbstractContentElementController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ZoteroAttachmentResolver $attachmentResolver,
        private readonly ZoteroLocaleLabelService $localeLabelService,
        private readonly ContentUrlGenerator $contentUrlGenerator,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $mode = (string) ($model->zotero_item_mode ?? 'fixed');
        $item = null;

        if ($mode === 'from_url') {
            $item = $this->resolveItemFromUrl($model);
            if ($item === null) {
                $autoItem = Input::get('auto_item');
                $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
                if ($autoItem !== null && $autoItem !== '' && $libraryIds !== []) {
                    throw new PageNotFoundException('Zotero-Item nicht gefunden: ' . $autoItem);
                }

                return new Response('', Response::HTTP_OK);
            }
        } else {
            $itemId = (int) ($model->zotero_item_id ?? 0);
            if ($itemId > 0) {
                $item = ZoteroItemModel::findByPk($itemId);
                if ($item !== null && $item->published !== '1') {
                    $item = null;
                }
            }
        }

        if ($item === null) {
            return new Response('', Response::HTTP_OK);
        }

        $itemTemplate = (string) ($model->zotero_template ?? 'cite_content');
        $itemArray = $this->itemToArray($item);
        if ($itemTemplate === 'json_dl') {
            $data = $itemArray['data'] ?? [];
            $keys = \is_array($data) ? array_keys($data) : [];
            $itemArray['field_labels'] = $this->localeLabelService->getItemFieldLabelsForKeys($keys, $this->resolveLocale($request));
        }

        $downloadAttachments = ($model->zotero_download_attachments ?? '1') === '1';
        $contentTypesFilter = $this->parseContentTypes($model->zotero_download_content_types ?? '');
        $filenameMode = $downloadAttachments ? ($model->zotero_download_filename_mode ?? 'cleaned') : null;
        $byItem = $this->attachmentResolver->getDownloadableAttachmentsForItems($this->connection, [(int) $item->id], $contentTypesFilter, $filenameMode);
        $itemArray['attachments'] = $byItem[(int) $item->id] ?? [];

        $template->item = $itemArray;
        $template->item_template = $itemTemplate;
        $template->download_attachments = $downloadAttachments;

        if ($mode === 'from_url') {
            $this->addOverviewPageToTemplate($template, $model);
        }

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? $item->title,
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
    }

    private function addOverviewPageToTemplate(FragmentTemplate $template, ContentModel $model): void
    {
        $overviewPageId = (int) ($model->zotero_overview_page ?? 0);
        if ($overviewPageId <= 0) {
            return;
        }

        $overviewPage = PageModel::findPublishedById($overviewPageId);
        if ($overviewPage === null) {
            return;
        }

        $template->referer = $this->contentUrlGenerator->generate($overviewPage);
        $customLabel = (string) ($model->zotero_overview_label ?? '');
        $template->back = $customLabel !== ''
            ? $customLabel
            : (string) ($GLOBALS['TL_LANG']['MSC']['zoteroOverview'] ?? 'Zurück zur Publikationsübersicht');
    }

    private function resolveItemFromUrl(ContentModel $model): ZoteroItemModel|null
    {
        $autoItem = Input::get('auto_item');
        if ($autoItem === null || $autoItem === '') {
            return null;
        }

        $libraryIds = $this->parseLibraryIds($model->zotero_libraries ?? '');
        if ($libraryIds === []) {
            return null;
        }

        return ZoteroItemModel::findPublishedByParentAndIdOrAliasInLibraries($autoItem, $libraryIds);
    }

    /**
     * Parst zotero_download_content_types (serialisiert). Leer = alle Typen.
     *
     * @return list<string>|null null = alle, [] = keine, [...]= gefilterte Content-Types
     */
    private function parseContentTypes(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        $arr = unserialize($value, ['allowed_classes' => false]);
        if (!\is_array($arr)) {
            return null;
        }
        $types = array_values(array_filter(array_map('strval', $arr), static fn (string $v): bool => $v !== ''));
        if ($types === []) {
            return null;
        }

        return $types;
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
