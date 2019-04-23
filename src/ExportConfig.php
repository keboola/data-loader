<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Configuration\File;
use Keboola\InputMapping\Configuration\Table;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ExportConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('configuration');
        $definition = $root->children()
            ->arrayNode('parameters')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('type')
                        // in future, this should be required, but for bwd compatibility is is not yet
                        ->defaultValue('python')
                        ->validate()
                            ->ifNotInArray(['r', 'python'])
                            ->thenInvalid('Invalid sandbox type: %s. Valid values are: "r", "python".')
                        ->end()
                    ->end()
                    ->arrayNode('script')
                        ->prototype('scalar')
                        ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('storage')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('input')
                    ->addDefaultsIfNotSet()
                    ->children();
        File::configureNode($definition->arrayNode('files')->prototype('array'));
        Table::configureNode($definition->arrayNode('tables')->prototype('array'));
        return $treeBuilder;
    }
}
