<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Stellt den Backend-Menüeintrag "bibliography" (Literaturverwaltung)
 * als letzten Punkt in der Kategorie "Inhalte" (content) dar.
 *
 * Das Menü wird per KnpMenu aus BE_MOD gebaut; die Reihenfolge lässt sich
 * über config.php nicht zuverlässig steuern. Daher verschieben wir den
 * Knoten im Event backend_menu_build ans Ende der content-Kinder.
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -255)]
class BackendMenuBibliographyPositionListener
{
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
        if (!$contentNode->getChild('bibliography')) {
            return;
        }

        $bibliographyItem = $contentNode->getChild('bibliography');
        $contentNode->removeChild('bibliography');
        $contentNode->addChild($bibliographyItem);
    }
}
