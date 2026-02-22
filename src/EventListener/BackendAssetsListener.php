<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bindet backend.css im Backend ein (u. a. Icon für Menü-Kategorie „Literaturverwaltung“).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
class BackendAssetsListener
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

        if (!$this->scopeMatcher->isBackendMainRequest($event)) {
            return;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/raum51contaozotero/css/backend.css|static';
    }
}
