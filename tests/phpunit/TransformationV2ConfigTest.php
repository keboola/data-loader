<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\TransformationV2Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class TransformationV2ConfigTest extends TestCase
{
    /**
     * @dataProvider configurationProvider
     * @param array $input
     * @param array $expected
     */
    public function testSuccess(array $input, array $expected): void
    {
        $processor = new Processor();
        $outData = $processor->processConfiguration(new TransformationV2Config(), ['configuration' => $input]);
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
                        'blocks' => [],
                    ],
                ],
            ],
            'extra stuff' => [
                [
                    'storage' => [],
                    'parameters' => [],
                    'variables_id' => '1234143',
                    'variables_values_id' => '314123',
                    'shared_code_id' => '23424553',
                    'shared_code_row_ids' => ['6535334']
                ],
                [
                    'storage' => [
                        'input' => [
                            'files' => [],
                            'tables' => [],
                        ],
                    ],
                    'parameters' => [
                        'type' => 'python',
                        'blocks' => [],
                    ],
                    'variables_id' => '1234143',
                    'variables_values_id' => '314123',
                    'shared_code_id' => '23424553',
                    'shared_code_row_ids' => ['6535334']
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
                        'type' => 'python',
                        'blocks' => [
                            [
                                'name' => 'Block 1',
                                'codes' => [
                                    [
                                        'name' => 'Code 1.1',
                                        'script' => [
                                            "print('hello code block 1')\n\nprint('end')"
                                        ],
                                    ],[
                                        'name' => 'Code 1.2',
                                        'script' => [
                                            "print('what is this?')"
                                        ],
                                    ],
                                ],
                            ],[
                                'name' => 'block2',
                                'codes' => [
                                    [
                                        'name' => 'Code 2.1',
                                        'script' => [
                                            "print('this is block 2')\n\nprint('end block 2')"
                                        ],
                                    ],
                                ],
                            ],
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
                        'blocks' => [
                            [
                                'name' => 'Block 1',
                                'codes' => [
                                    [
                                        'name' => 'Code 1.1',
                                        'script' => [
                                            "print('hello code block 1')\n\nprint('end')"
                                        ],
                                    ],[
                                        'name' => 'Code 1.2',
                                        'script' => [
                                            "print('what is this?')"
                                        ],
                                    ],
                                ],
                            ],[
                                'name' => 'block2',
                                'codes' => [
                                    [
                                        'name' => 'Code 2.1',
                                        'script' => [
                                            "print('this is block 2')\n\nprint('end block 2')"
                                        ],
                                    ],
                                ],
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
            'parameters' => [
                'type' => 'python',
            ],
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'invalidKey' => 'value',
                        ],
                    ],
                ],
            ],
        ];
        $processor = new Processor();
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage(
            'Unrecognized option "invalidKey" under "configuration.storage.input.tables.0". Available options are "changed_since", "column_types", "columns", "days", "destination", "limit", "source", "source_search", "where_column", "where_operator", "where_values"'
        );
        $processor->processConfiguration(new TransformationV2Config(), ['configuration' => $inData]);
    }

    public function testIgnoreOutputMapping(): void
    {
        $inData = [
            'parameters' => [
                'type' => 'python',
            ],
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.test',
                            'destination' => 'test',
                        ],
                    ],
                ],
                'output' => [
                    'tables' => [
                        [
                            'source' =>  'test',
                            'destination' => 'out.c-main.test',
                        ],
                    ],
                ],
            ],
        ];
        $expected = [
            'storage' => [
                'input' => [
                    'files' => [],
                    'tables' => [
                        [
                            'source' => 'in.c-main.test',
                            'destination' => 'test',
                            'columns' => [],
                            'column_types' => [],
                            'where_values' => [],
                            'where_operator' => 'eq',
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'type' => 'python',
                'blocks' => [],
            ],
        ];
        $processor = new Processor();
        $outData = $processor->processConfiguration(new TransformationV2Config(), ['configuration' => $inData]);
        self::assertEquals($expected, $outData);
    }
}
