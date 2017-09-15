<?php

namespace Keboola\DataLoader;

use Keboola\InputMapping\Configuration\File;
use Keboola\InputMapping\Configuration\Table;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TransformationConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('configuration')->ignoreExtraKeys(true);
        $definition = $root->children()
            ->arrayNode('configuration')
            ->ignoreExtraKeys(true)
            ->children()
                ->scalarNode('backend')->isRequired()->end()
                ->scalarNode('type')->isRequired()->end()
                ->arrayNode('queries')->prototype('scalar')->isRequired()->end()->end()
                ->arrayNode('input')
                ->prototype('array');
        Table::configureNode($definition);
        return $treeBuilder;
    }
}
