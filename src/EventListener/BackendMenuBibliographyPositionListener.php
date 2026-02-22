<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Knp\Menu\Util\MenuManipulator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Positioniert die Backend-Menü-Kategorie "Literaturverwaltung" (BE_MOD['zotero'])
 * direkt nach "Inhalte" (Content).
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -512)]
class BackendMenuBibliographyPositionListener
{
    /** Position 2 = nach "Inhalte" (Content), 0 = Favorites, 1 = Content */
    private const TARGET_POSITION = 2;

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();
        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        $node = $tree->getChild('zotero');
        if ($node === null) {
            return;
        }

        $manipulator = new MenuManipulator();
        $manipulator->moveToPosition($node, self::TARGET_POSITION);
    }
}
