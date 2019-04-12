<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\TransformationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class TransformationConfigTest extends TestCase
{
    /**
     * @dataProvider configurationProvider
     * @param array $input
     * @param array $expected
     */
    public function testSuccess(array $input, array $expected): void
    {
        $processor = new Processor();
        $outData = $processor->processConfiguration(new TransformationConfig(), ['configuration' => $input]);
        self::assertEquals($expected, $outData);
    }

    public function configurationProvider(): array
    {
        return [
            'empty config' => [
                [],
                [],
            ],
            'simple config' => [
                [
                    'configuration' => [
                        'backend' => 'docker',
                        'type' => 'r',
                    ],
                ],
                [
                    'configuration' => [
                        'backend' => 'docker',
                        'type' => 'r',
                        'queries' => [],
                        'tags' => [],
                        'input' => [],
                    ],
                ],
            ],
            'full config' => [
                [
                    'configuration' => [
                        'backend' => 'docker',
                        'type' => 'r',
                        'queries' => ['foo', 'bar'],
                        'tags' => ['first', 'second'],
                        'anExtraKey' => 'mustBeIgnored',
                        'input' => [
                            [
                                'source' => 'in.c-main',
                                'destination' => 'my.csv',
                                'changed_since' => null,
                                'where_column' => 'a',
                                'where_values' => ['e', 'f'],
                                'where_operator' => 'eq',
                                'limit' => 10,
                                'changedSince' => '-1 day',
                                'columns' => ['x', 'y'],
                                'whereColumn' => 'x',
                                'whereValues' => ['z', 'zz'],
                                'whereOperator' => 'ne',
                                'thisIsExtra' => 'too',
                            ],
                        ],
                    ],
                ],
                [
                    'configuration' => [
                        'backend' => 'docker',
                        'type' => 'r',
                        'queries' => ['foo', 'bar'],
                        'tags' => ['first', 'second'],
                        'input' => [
                            [
                                'source' => 'in.c-main',
                                'destination' => 'my.csv',
                                'changed_since' => '',
                                'where_column' => 'a',
                                'where_values' => ['e', 'f'],
                                'where_operator' => 'eq',
                                'limit' => 10,
                                'changedSince' => '-1 day',
                                'columns' => ['x', 'y'],
                                'whereColumn' => 'x',
                                'whereValues' => ['z', 'zz'],
                                'whereOperator' => 'ne',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testInvalid(): void
    {
        $inData = [
            'configuration' => [
                'backend' => 'docker',
                'type' => 'r',
                'input' => [
                    [],
                ],
            ],
        ];
        $processor = new Processor();
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage(
            'The child node "source" at path "configuration.configuration.input.0" must be configured.'
        );
        $processor->processConfiguration(new TransformationConfig(), ['configuration' => $inData]);
    }
}
