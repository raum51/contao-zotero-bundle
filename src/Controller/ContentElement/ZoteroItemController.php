<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Raum51\ContaoZoteroBundle\Model\ZoteroItemModel;
use Raum51\ContaoZoteroBundle\Service\ZoteroAttachmentResolver;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Raum51\ContaoZoteroBundle\Service\ZoteroSchemaOrgService;
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
    private const META_DESCRIPTION_MAX_LENGTH = 160;

    public function __construct(
        private readonly Connection $connection,
        private readonly ZoteroAttachmentResolver $attachmentResolver,
        private readonly ZoteroLocaleLabelService $localeLabelService,
        private readonly ZoteroSchemaOrgService $schemaOrgService,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly HtmlDecoder $htmlDecoder,
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

        $canonicalUrl = (string) $request->getUri();
        $schemaOrgData = $this->schemaOrgService->generateFromItem($itemArray, $canonicalUrl);
        if ($schemaOrgData !== null) {
            $itemArray['schema_org_data'] = $schemaOrgData;
        }

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
            $this->setPageMetaFromItem($item, $itemArray, $canonicalUrl);
        }

        $headlineData = StringUtil::deserialize($model->headline ?? '', true);
        $template->headline = [
            'text' => $headlineData['value'] ?? $item->title,
            'tag_name' => $headlineData['unit'] ?? 'h2',
        ];

        return $template->getResponse();
    }

    private function setPageMetaFromItem(ZoteroItemModel $item, array $itemArray, string $canonicalUrl): void
    {
        $responseContext = $this->responseContextAccessor->getResponseContext();
        if ($responseContext === null || !$responseContext->has(HtmlHeadBag::class)) {
            return;
        }

        $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);

        // Title: Nur der Seitenanteil; Contao ergänzt per titleTag automatisch " - Website-Name"
        $title = $this->htmlDecoder->inputEncodedToPlainText($itemArray['title'] ?? '');
        if ($title !== '') {
            $htmlHeadBag->setTitle($title);
        }

        // Meta Description: cite_content (HTML-frei) oder Fallback "Autor(en) (Jahr): Titel"
        $metaDescription = $this->buildMetaDescription($item, $itemArray);
        if ($metaDescription !== '') {
            $htmlHeadBag->setMetaDescription($metaDescription);
        }

        $htmlHeadBag->setCanonicalUri($canonicalUrl);

        // Meta Keywords: Zotero-Tags als kommaseparierte Liste (Contao-Suchindex profitiert)
        $keywords = $this->getTagsAsCommaSeparated($item->tags ?? '');
        if ($keywords !== '') {
            $htmlHeadBag->addMetaTag((new HtmlAttributes())->set('name', 'keywords')->set('content', $keywords));
        }
    }

    /**
     * Liefert Tags als kommaseparierte Liste für Meta Keywords.
     * Unterstützt: neues Format ", " (DB) und Legacy-JSON [{"tag":"x"},...].
     */
    private function getTagsAsCommaSeparated(string $tagsStored): string
    {
        if ($tagsStored === '') {
            return '';
        }
        // Legacy-JSON (vor Migration)
        if (str_starts_with(trim($tagsStored), '[')) {
            $tags = json_decode($tagsStored, true);
            if (!\is_array($tags)) {
                return '';
            }
            $names = [];
            foreach ($tags as $t) {
                if (!\is_array($t)) {
                    continue;
                }
                $name = trim((string) ($t['tag'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return implode(', ', array_unique($names));
        }

        // Neues Format: ", " getrennt
        $parts = array_filter(array_map('trim', explode(', ', $tagsStored)));

        return implode(', ', array_unique($parts));
    }

    /**
     * Baut die Meta Description: cite_content (HTML-frei, max. 160 Zeichen) oder
     * Fallback "Autor(en) (Jahr): Titel".
     */
    private function buildMetaDescription(ZoteroItemModel $item, array $itemArray): string
    {
        $citeContent = trim((string) ($item->cite_content ?? ''));
        if ($citeContent !== '') {
            $plain = $this->htmlDecoder->htmlToPlainText($citeContent);
            $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
            $plain = trim($plain);
            if ($plain !== '') {
                return \strlen($plain) > self::META_DESCRIPTION_MAX_LENGTH
                    ? mb_substr($plain, 0, self::META_DESCRIPTION_MAX_LENGTH - 1) . '…'
                    : $plain;
            }
        }

        $authors = $this->getAuthorsFromJsonData($item->json_data ?? '{}');
        $year = trim((string) ($item->year ?? ''));
        $title = trim((string) ($itemArray['title'] ?? ''));

        $prefix = $authors !== ''
            ? $authors . ($year !== '' ? ' (' . $year . ')' : '') . ': '
            : ($year !== '' ? '(' . $year . '): ' : '');

        $result = $prefix . ($title !== '' ? $title : 'Publikation');
        return \strlen($result) > self::META_DESCRIPTION_MAX_LENGTH
            ? mb_substr($result, 0, self::META_DESCRIPTION_MAX_LENGTH - 1) . '…'
            : $result;
    }

    /**
     * Liefert Autoren-String aus json_data.creators (Format: "Nachname, Vorname; …").
     */
    private function getAuthorsFromJsonData(string $jsonData): string
    {
        $data = json_decode($jsonData, true);
        if (!\is_array($data)) {
            return '';
        }
        $creators = $data['creators'] ?? [];
        if (!\is_array($creators)) {
            return '';
        }
        $parts = [];
        foreach ($creators as $c) {
            if (!\is_array($c)) {
                continue;
            }
            $name = trim((string) ($c['name'] ?? ''));
            if ($name !== '') {
                $parts[] = $name;
                continue;
            }
            $last = trim((string) ($c['lastName'] ?? ''));
            $first = trim((string) ($c['firstName'] ?? ''));
            $part = $last !== '' ? ($first !== '' ? $last . ', ' . $first : $last) : ($first !== '' ? $first : '');
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode('; ', $parts);
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
