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
            ->arrayNode('storage')
                ->beforeNormalization()
                    ->ifEmpty()
                    ->then(function () {
                        return ['input' => []];
                    })
                ->end()
            ->children()
            ->arrayNode('input')
                ->beforeNormalization()
                    ->ifEmpty()
                    ->then(function () {
                        return ['files' => [], 'tables' => []];
                    })
                ->end()
            ->children();
        File::configureNode($definition->arrayNode('files')->prototype('array'));
        Table::configureNode($definition->arrayNode('tables')->prototype('array'));
        return $treeBuilder;
    }
}
