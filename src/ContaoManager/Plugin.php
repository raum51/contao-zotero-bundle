<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Raum51\ContaoZoteroBundle\Raum51ContaoZoteroBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Registriert das Zotero-Bundle in der Contao Managed Edition.
 * Wird über extra.contao-manager-plugin in composer.json geladen.
 * setLoadAfter(ContaoCoreBundle) sorgt dafür, dass DCA/Übersetzungen des Core erweitert werden können.
 * RoutingPluginInterface: Routen werden über den Manager registriert und vor dem Contao-Content-Routing geladen
 * (laut Doku: „load your routes in your Plugin class“ / Back End Routes Guide).
 */
final class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(Raum51ContaoZoteroBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection
    {
        $file = __DIR__ . '/../../Resources/config/routes.yaml';

        return $resolver->resolve($file)->load($file);
    }
}
