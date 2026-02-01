
<?php

namespace raum51\ContaoZoteroBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('raum51_contao_zotero');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('user_agent')->defaultValue('raum51-contao-zotero-bundle/1.0')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
