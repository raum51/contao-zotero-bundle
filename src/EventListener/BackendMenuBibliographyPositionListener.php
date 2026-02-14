<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Gruppiert die Zotero-Backend-Menüeinträge unter "Literaturverwaltung".
 *
 * Bibliotheken, Autoren-Zuordnung und Locales werden als Kinder eines
 * übergeordneten Menüpunkts "Literaturverwaltung" angezeigt.
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -255)]
class BackendMenuBibliographyPositionListener
{
    private const ZOTERO_MENU_IDS = ['bibliography', 'tl_zotero_creator_map', 'tl_zotero_locales'];

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();
        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        if (!$tree->getChild('content')) {
            return;
        }

        $contentNode = $tree->getChild('content');

        $children = [];
        foreach (self::ZOTERO_MENU_IDS as $menuId) {
            $child = $contentNode->getChild($menuId);
            if ($child !== null) {
                $contentNode->removeChild($menuId);
                $children[] = $child;
            }
        }

        if ($children === []) {
            return;
        }

        $factory = $event->getFactory();
        $groupLabel = $GLOBALS['TL_LANG']['MOD']['bibliography_group'][0] ?? 'Literaturverwaltung';

        $groupNode = $factory
            ->createItem('literaturverwaltung')
            ->setLabel($groupLabel)
            ->setUri('#');

        foreach ($children as $child) {
            $groupNode->addChild($child);
        }

        $contentNode->addChild($groupNode);
    }
}
