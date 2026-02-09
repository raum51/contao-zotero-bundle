<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle;

use Raum51\ContaoZoteroBundle\DependencyInjection\Raum51ContaoZoteroExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Contao-Zotero-Bundle: Integration der Zotero API (Items, Collections, Literaturnachweise, .bib-Export).
 * Bundle-Klasse im src/-Verzeichnis, damit der PSR-4-Autoload des composer.json greift.
 */
final class Raum51ContaoZoteroBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new Raum51ContaoZoteroExtension();
    }
}
