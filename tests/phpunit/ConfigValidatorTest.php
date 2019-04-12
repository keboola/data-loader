<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\ConfigValidator;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        if (empty(getenv('KBC_TEST_TOKEN')) || empty(getenv('KBC_TEST_URL'))) {
            self::fail('KBC_TEST_TOKEN and KBC_TEST_URL environment variables must be set.');
        }
        $this->client = new Client(['token' => getenv('KBC_TEST_TOKEN'), 'url' => getenv('KBC_TEST_URL')]);
        putenv('KBC_TOKEN=' . getenv('KBC_TEST_TOKEN'));
        putenv('KBC_STORAGEAPI_URL=' . getenv('KBC_TEST_URL'));
        putenv('KBC_CONFIG_ID=');
        putenv('KBC_ROW_ID=');
        putenv('KBC_CONFIG_VERSION=');
        putenv('KBC_EXPORT_CONFIG=');
        parent::setUp();
    }

    public function testValidateTransformationConfigEmpty(): void
    {
        $component = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setName('data-loader-test');
        $configuration->setComponentId('transformation');
        $configId = $component->addConfiguration($configuration)['id'];
        $configuration->setConfigurationId($configId);
        $configurationRow = new ConfigurationRow($configuration);
        $configurationRow->setConfiguration(['type' => 'r', 'backend' => 'docker']);
        $configurationRow->setName('test-row');
        $rowId = $component->addConfigurationRow($configurationRow)['id'];

        putenv('KBC_CONFIG_ID=' . $configId);
        putenv('KBC_ROW_ID=' . $rowId);
        putenv('KBC_CONFIG_VERSION=2');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::assertEquals('', $validator->getScript());
        self::assertEquals('/data/', $validator->getDataDir());
        self::assertEquals('R', $validator->getExtension());
        self::assertEquals(['tables' => []], $validator->getInput());
        self::assertNotEmpty($validator->getRunId());
        self::assertNotEmpty($validator->getClient());

        $component->deleteConfiguration('transformation', $configId);
    }

    public function testValidateTransformationConfigNotEmpty(): void
    {
        $component = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setName('data-loader-test');
        $configuration->setComponentId('transformation');
        $configId = $component->addConfiguration($configuration)['id'];
        $configuration->setConfigurationId($configId);
        $configurationRow = new ConfigurationRow($configuration);
        $configurationRow->setConfiguration(
            [
                'type' => 'python',
                'backend' => 'docker',
                'input' => [
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'whereColumn' => '',
                        'changedSince' => '-1 day',
                    ],
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'whereColumn' => 'id',
                        'whereOperator' => 'eq',
                        'whereValues' => ['68640847'],
                        'changedSince' => '',
                    ],
                ],
                'tags' => ['my-file'],
                'queries' => [
                    "this is some script\ncode on multiple lines",
                ],
            ]
        );
        $configurationRow->setName('test-row');
        $rowId = $component->addConfigurationRow($configurationRow)['id'];

        putenv('KBC_CONFIG_ID=' . $configId);
        putenv('KBC_ROW_ID=' . $rowId);
        putenv('KBC_CONFIG_VERSION=2');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::assertEquals("this is some script\ncode on multiple lines", $validator->getScript());
        self::assertEquals('/data/', $validator->getDataDir());
        self::assertEquals('py', $validator->getExtension());
        self::assertEquals(
            [
                'tables' => [
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'columns' => [],
                        'where_values' => [],
                        'where_operator' => 'eq',
                        'changed_since' => '-1 day',
                    ],
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'columns' => [],
                        'where_values' => ['68640847'],
                        'where_operator' => 'eq',
                        'where_column' => 'id',
                    ],
                ],
                'files' => [
                    [
                        'tags' => ['my-file'],
                    ],
                ],
            ],
            $validator->getInput()
        );
        self::assertNotEmpty($validator->getRunId());
        self::assertNotEmpty($validator->getClient());

        $component->deleteConfiguration('transformation', $configId);
    }

    public function testValidatePlainSandboxEmpty(): void
    {
        $configuration = ['storage' => ['input' => []]];
        putenv('KBC_EXPORT_CONFIG=' . json_encode($configuration));
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::assertEquals('', $validator->getScript());
        self::assertEquals('/data/', $validator->getDataDir());
        self::assertEquals(['tables' => [], 'files' => []], $validator->getInput());
        self::assertNotEmpty($validator->getRunId());
        self::assertNotEmpty($validator->getClient());
    }

    public function testValidatePlainSandboxNotEmpty(): void
    {
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                        ],
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'destination.csv',
                            'where_values' => ['68640847'],
                            'where_operator' => 'eq',
                            'where_column' => 'id',
                        ],
                    ],
                    'files' => [
                        [
                            'tags' => ['my-file'],
                        ],
                    ],
                ],
            ],
        ];
        putenv('KBC_EXPORT_CONFIG=' . json_encode($configuration));
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::assertEquals('', $validator->getScript());
        self::assertEquals('/data/', $validator->getDataDir());
        self::assertEquals(
            [
                'tables' => [
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'columns' => [],
                        'where_values' => [],
                        'where_operator' => 'eq',
                    ],
                    [
                        'source' => 'in.c-main.source',
                        'destination' => 'destination.csv',
                        'columns' => [],
                        'where_values' => ['68640847'],
                        'where_operator' => 'eq',
                        'where_column' => 'id',
                    ],
                ],
                'files' => [
                    [
                        'tags' => ['my-file'],
                        'limit' => 10,
                        'processed_tags' => [],
                    ],
                ],
            ],
            $validator->getInput()
        );
        self::assertNotEmpty($validator->getRunId());
        self::assertNotEmpty($validator->getClient());
    }

    public function testEmptyToken(): void
    {
        putenv('KBC_TOKEN=');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(170);
        self::expectExceptionMessage('Environment KBC_TOKEN is empty.');
        $validator->validate($logger);
    }

    public function testRunId(): void
    {
        $configuration = ['storage' => ['input' => []]];
        putenv('KBC_EXPORT_CONFIG=' . json_encode($configuration));
        putenv('KBC_RUNID=987654321');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::assertEquals('', $validator->getScript());
        self::assertEquals('/data/', $validator->getDataDir());
        self::assertEquals(['tables' => [], 'files' => []], $validator->getInput());
        self::assertContains('987654321', $validator->getRunId());
        self::assertNotEmpty($validator->getClient());
    }

    public function testNoConfig(): void
    {
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(170);
        self::expectExceptionMessage('Environment KBC_EXPORT_CONFIG is empty.');
        $validator->validate($logger);
    }

    public function testInvalidPlainConfigJson(): void
    {
        putenv('KBC_EXPORT_CONFIG={"a"b}');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(0);
        self::expectExceptionMessage('Input configuration is invalid: Syntax error');
        $validator->validate($logger);
    }

    public function testInvalidPlainConfig(): void
    {
        $configuration = ['bad-node' => ['input' => []]];
        putenv('KBC_EXPORT_CONFIG=' . json_encode($configuration));
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(171);
        self::expectExceptionMessage(
            'Configuration is invalid: Unrecognized option "bad_node" ' .
            'under "configuration". Available option is "storage".'
        );
        $validator->validate($logger);
    }

    public function testInvalidTransformationConfigMissingRowIdConfigVersion(): void
    {
        putenv('KBC_CONFIG_ID=123');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(170);
        self::expectExceptionMessage('Environment KBC_CONFIG_ID or KBC_ROW_ID or KBC_CONFIG_VERSION is empty.');
        $validator->validate($logger);
    }

    public function testInvalidTransformationConfigMissingConfigVersion(): void
    {
        putenv('KBC_CONFIG_ID=123');
        putenv('KBC_ROW_ID=123');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(170);
        self::expectExceptionMessage('Environment KBC_CONFIG_ID or KBC_ROW_ID or KBC_CONFIG_VERSION is empty.');
        $validator->validate($logger);
    }

    public function testInvalidTransformationConfigMissingRowId(): void
    {
        putenv('KBC_CONFIG_ID=123');
        putenv('KBC_CONFIG_VERSION=123');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(170);
        self::expectExceptionMessage('Environment KBC_CONFIG_ID or KBC_ROW_ID or KBC_CONFIG_VERSION is empty.');
        $validator->validate($logger);
    }

    public function testNonExistentTransformationConfig(): void
    {
        putenv('KBC_CONFIG_ID=123');
        putenv('KBC_ROW_ID=123');
        putenv('KBC_CONFIG_VERSION=123');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(171);
        self::expectExceptionMessage('Failed to get configuration: Configuration 123 not found');
        $validator->validate($logger);
    }

    public function testNonExistentTransformationConfigRow(): void
    {
        $component = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setName('data-loader-test');
        $configuration->setComponentId('transformation');
        $configId = $component->addConfiguration($configuration)['id'];
        $configuration->setConfigurationId($configId);

        putenv('KBC_CONFIG_ID=' . $configId);
        putenv('KBC_ROW_ID=123');
        putenv('KBC_CONFIG_VERSION=1');
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(171);
        self::expectExceptionMessage('Configuration Row not found.');
        $validator->validate($logger);
    }

    public function testInvalidTransformationConfigData(): void
    {
        $component = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setName('data-loader-test');
        $configuration->setComponentId('transformation');
        $configId = $component->addConfiguration($configuration)['id'];
        $configuration->setConfigurationId($configId);
        $configurationRow = new ConfigurationRow($configuration);
        $configurationRow->setConfiguration(['something' => 'quite bad']);
        $configurationRow->setName('test-row');
        $rowId = $component->addConfigurationRow($configurationRow)['id'];

        putenv('KBC_CONFIG_ID=' . $configId);
        putenv('KBC_ROW_ID=' . $rowId);
        putenv('KBC_CONFIG_VERSION=2');

        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(171);
        self::expectExceptionMessage(
            'Configuration is invalid: The child node "backend" ' .
            'at path "configuration.configuration" must be configured.'
        );
        $validator->validate($logger);
    }

    public function testValidatePlainSandboxExtension(): void
    {
        $configuration = ['storage' => ['input' => []]];
        putenv('KBC_EXPORT_CONFIG=' . json_encode($configuration));
        $logger = new Logger('test', [new NullHandler()]);
        $validator = new ConfigValidator();
        $validator->validate($logger);

        self::expectException(InvalidInputException::class);
        self::expectExceptionCode(171);
        self::expectExceptionMessage('Invalid transformation type "".');
        $validator->getExtension();
    }
}
