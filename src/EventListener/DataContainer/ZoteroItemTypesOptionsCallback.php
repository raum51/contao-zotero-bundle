<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Raum51\ContaoZoteroBundle\Service\ZoteroLocaleLabelService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Liefert Zotero-Item-Typen aus tl_zotero_locales als Options für die Modul-Konfiguration.
 *
 * Liegt unter EventListener/DataContainer/, da es ein DCA-Callback für tl_module ist.
 * Labels in Backend-Sprache (oder en_US-Fallback).
 */
#[AsCallback(table: 'tl_module', target: 'fields.zotero_item_types.options')]
#[AsCallback(table: 'tl_content', target: 'fields.zotero_item_types.options')]
final class ZoteroItemTypesOptionsCallback
{
    public function __construct(
        private readonly ZoteroLocaleLabelService $localeLabelService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string> item_type_key => label
     */
    public function __invoke(DataContainer|null $dc = null): array
    {
        $locale = $this->translator->getLocale();
        if ($locale === '') {
            $locale = 'en_US';
        }

        $labels = $this->localeLabelService->getAllItemTypeLabels($locale);

        return $labels;
    }
}
