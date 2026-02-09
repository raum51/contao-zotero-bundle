<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Raum51\ContaoZoteroBundle\Raum51ContaoZoteroBundle;

/**
 * Registriert das Zotero-Bundle in der Contao Managed Edition.
 * Wird über extra.contao-manager-plugin in composer.json geladen.
 * setLoadAfter(ContaoCoreBundle) sorgt dafür, dass DCA/Übersetzungen des Core erweitert werden können.
 */
final class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(Raum51ContaoZoteroBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
