<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DatadirTests\DatadirTestSpecification;

class PlainSandboxTest extends BaseDatadirTest
{
    public function testBasicPython(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'script' => [
                    'abc',
                ],
            ],
        ];
        $envs = [
            'KBC_EXPORT_CONFIG' => json_encode($configuration),
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/plain-sandbox/source/data',
            0,
            null,
            '',
            __DIR__ . '/plain-sandbox/expected/data/in'
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $output = $process->getOutput();
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 1 local table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
        $scriptFile = $tempDatadir->getTmpFolder() . '/notebook.ipynb';
        self::assertFileExists($scriptFile);
        $script = json_decode(file_get_contents($scriptFile), true);
        self::assertEquals(["abc\n"], $script['cells'][0]['source']);
        self::assertEquals('python', $script['metadata']['kernelspec']['language']);
    }

    public function testBasicTest(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'script' => [
                    'abc',
                ],
                'type' => 'test',
            ],
        ];
        $envs = [
            'KBC_EXPORT_CONFIG' => json_encode($configuration),
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/plain-sandbox/source/data',
            0,
            null,
            '',
            __DIR__ . '/plain-sandbox/expected/data/in'
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $output = $process->getOutput();
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 1 local table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
        $scriptFile = $tempDatadir->getTmpFolder() . '/notebook.ipynb';
        self::assertFileExists($scriptFile);
        $script = json_decode(file_get_contents($scriptFile), true);
        self::assertEquals(["abc\n"], $script['cells'][0]['source']);
        self::assertEquals('python', $script['metadata']['kernelspec']['language']);
    }

    public function testBasicJulia(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'script' => [
                    'abc',
                ],
                'type' => 'julia',
            ],
        ];
        $envs = [
            'KBC_EXPORT_CONFIG' => json_encode($configuration),
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/plain-sandbox/source/data',
            0,
            null,
            '',
            __DIR__ . '/plain-sandbox/expected/data/in'
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $output = $process->getOutput();
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 1 local table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
        $scriptFile = $tempDatadir->getTmpFolder() . '/notebook.ipynb';
        self::assertFileExists($scriptFile);
        $scriptFile = $tempDatadir->getTmpFolder() . '/notebook.ipynb';
        self::assertFileExists($scriptFile);
        $script = json_decode(file_get_contents($scriptFile), true);
        self::assertEquals(["abc\n"], $script['cells'][0]['source']);
        self::assertEquals('julia', $script['metadata']['kernelspec']['language']);
    }

    public function testFiltered(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                            'changed_since' => '-1 seconds',
                        ],
                    ],
                ],
            ],
        ];
        $envs = [
            'KBC_EXPORT_CONFIG' => json_encode($configuration),
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/plain-sandbox-filtered/source/data',
            0,
            null,
            '',
            __DIR__ . '/plain-sandbox-filtered/expected/data/in'
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $output = $process->getOutput();
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: The script is empty.  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 1 local table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
    }

    public function testInvalidConfiguration(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'invalid' => 'configuration',
                        ],
                    ],
                ],
            ],
        ];
        $envs = [
            'KBC_EXPORT_CONFIG' => json_encode($configuration),
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/plain-sandbox-invalid-configuration/source/data',
            171,
            null,
            '',
            __DIR__ . '/plain-sandbox-invalid-configuration/expected/data/in'
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $output = $process->getOutput();
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.ERROR: Configuration is invalid: Unrecognized option "invalid" under ' .
                    '"configuration.storage.input.tables.0". Available options are "changed_since", "columns", ' .
                    '"days", "destination", "limit", "source", "source_search", "where_column", ' .
                    '"where_operator", "where_values".  ',
                '',
            ]),
            $output
        );
    }
}
