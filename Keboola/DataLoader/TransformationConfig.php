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
        $root = $treeBuilder->root('configuration');
        $definition = $root->children()
            ->arrayNode('configuration')
            ->children()
            ->scalarNode('backend')->isRequired()->end()
            ->scalarNode('type')->isRequired()->end()
            ->arrayNode('queries')->prototype('scalar')->isRequired()->end()
            ->arrayNode('input')
            ->ignoreExtraKeys(true)
            ->children();
        File::configureNode($definition->arrayNode('files')->prototype('array'));
        Table::configureNode($definition->arrayNode('tables')->prototype('array'));
        return $treeBuilder;
    }
}
