<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bindet zotero.css im Frontend ein.
 *
 * Contao-Doku empfiehlt für Assets einen kernel.request-Listener statt config.php,
 * da config.php beim Mergen der Config geladen wird (ggf. ohne Request/Container).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
class FrontendAssetsListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->scopeMatcher->isFrontendRequest($event->getRequest())) {
            return;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/raum51contaozotero/css/zotero.css|static';
    }
}
