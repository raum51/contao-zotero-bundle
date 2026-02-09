<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Lädt die Service-Definitionen des Bundles (Resources/config/services.yaml).
 * Liegt in DependencyInjection/, damit Symfony die Extension automatisch erkennt,
 * wenn die Bundle-Klasse getContainerExtension() darauf verweist.
 */
final class Raum51ContaoZoteroExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
