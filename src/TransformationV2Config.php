<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Configuration\File;
use Keboola\InputMapping\Configuration\Table;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TransformationV2Config implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('configuration');
        $definition = $root->ignoreExtraKeys(false)->children()
            ->arrayNode('parameters')
                ->addDefaultsIfNotSet()
                ->ignoreExtraKeys(true)
                ->children()
                    ->scalarNode('type')
                    // in future, this should be required, but for bwd compatibility is is not yet
                        ->defaultValue('python')
                        ->validate()
                            ->ifNotInArray(['r', 'python', 'julia', 'test'])
                            ->thenInvalid('Invalid sandbox type: %s. Valid values are: "r", "python", "julia", "test".')
                        ->end()
                    ->end()
                    ->arrayNode('blocks')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('name')->end()
                                ->arrayNode('codes')
                                    ->prototype('array')
                                        ->children()
                                            ->scalarNode('name')->end()
                                            ->arrayNode('script')
                                                ->prototype('scalar')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('variableValuesData')->end()
            ->scalarNode('variableValuesId')->end()
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
