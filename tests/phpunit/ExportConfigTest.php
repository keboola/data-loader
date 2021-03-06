<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\ExportConfig;
use Keboola\DataLoader\TransformationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ExportConfigTest extends TestCase
{
    /**
     * @dataProvider configurationProvider
     * @param array $input
     * @param array $expected
     */
    public function testSuccess(array $input, array $expected): void
    {
        $processor = new Processor();
        $outData = $processor->processConfiguration(new ExportConfig(), ['configuration' => $input]);
        self::assertEquals($expected, $outData);
    }

    public function configurationProvider(): array
    {
        return [
            'empty config' => [
                [],
                [
                    'storage' => [
                        'input' => [
                            'files' => [],
                            'tables' => [],
                        ],
                    ],
                    'parameters' => [
                        'type' => 'python',
                        'script' => [],
                    ],
                ],
            ],
            'full config' => [
                [
                    'storage' => [
                        'input' => [
                            'tables' => [
                                [
                                    'source' => 'in.c-main',
                                    'destination' => 'my.csv',
                                    'changed_since' => null,
                                    'where_column' => 'a',
                                    'where_values' => ['e', 'f'],
                                    'where_operator' => 'eq',
                                    'limit' => 10,
                                    'columns' => ['x', 'y'],
                                ],
                            ],
                            'files' => [
                                [
                                    'tags' => ['test'],
                                ],
                            ],
                        ],
                    ],
                    'parameters' => [
                        'script' => [
                            'abc',
                        ],
                    ],
                ],
                [
                    'storage' => [
                        'input' => [
                            'tables' => [
                                [
                                    'source' => 'in.c-main',
                                    'destination' => 'my.csv',
                                    'changed_since' => '',
                                    'where_column' => 'a',
                                    'where_values' => ['e', 'f'],
                                    'where_operator' => 'eq',
                                    'limit' => 10,
                                    'columns' => ['x', 'y'],
                                    'column_types' => [],
                                ],
                            ],
                            'files' => [
                                [
                                    'tags' => ['test'],
                                    'processed_tags' => [],
                                ],
                            ],
                        ],
                    ],
                    'parameters' => [
                        'type' => 'python',
                        'script' => ['abc'],
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
            ],
        ];
        $processor = new Processor();
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage(
            'Unrecognized option "configuration" under "configuration". Available options are "parameters", "storage".'
        );
        $processor->processConfiguration(new ExportConfig(), ['configuration' => $inData]);
    }
}
