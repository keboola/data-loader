<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TransformationConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('configuration')->ignoreExtraKeys(true);
        $definition = $root->children()
            ->arrayNode('configuration')
            ->ignoreExtraKeys(true)
            ->children()
                ->scalarNode('backend')->isRequired()->end()
                ->scalarNode('type')
                    ->isRequired()
                    ->validate()
                        ->ifNotInArray(['r', 'python', 'julia'])
                        ->thenInvalid('Invalid transformation type: %s. Valid values are: "r", "python", "julia".')
                    ->end()
                ->end()
                ->arrayNode('queries')->prototype('scalar')->defaultValue([])->end()->end()
                ->arrayNode('tags')->prototype('scalar')->defaultValue([])->end()->end()
                ->arrayNode('input')
                ->prototype('array');
        self::configureTableNode($definition);
        return $treeBuilder;
    }

    private static function configureTableNode(NodeDefinition $node): void
    {
        /* accept relevant stuff from
        https://github.com/keboola/transformation-bundle/blob/master/Resources/schemas/docker.json
        and \Keboola\InputMapping\Configuration::configurenode() */
        $node
            ->ignoreExtraKeys(true)
            ->children()
                ->scalarNode('source')->isRequired()->end()
                ->scalarNode('destination')->end()
                ->integerNode('days')->treatNullLike(0)->end()
                ->scalarNode('changed_since')->treatNullLike('')->end()
                ->arrayNode('columns')->prototype('scalar')->end()->end()
                ->integerNode('limit')->end()
                ->scalarNode('where_column')->end()
                ->arrayNode('where_values')->prototype('scalar')->end()->end()
                ->scalarNode('where_operator')
                    ->defaultValue('eq')
                    ->validate()
                        ->ifNotInArray(['eq', 'ne'])
                        ->thenInvalid('Invalid operator in where_operator %s.')
                    ->end()
                ->end()
                ->scalarNode('changedSince')->treatNullLike('')->end()
                ->scalarNode('whereColumn')->end()
                ->arrayNode('whereValues')->prototype('scalar')->end()->end()
                ->scalarNode('whereOperator')
                    ->defaultValue('eq')
                    ->validate()
                        ->ifNotInArray(['eq', 'ne'])
                        ->thenInvalid('Invalid operator in where_operator %s.')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
