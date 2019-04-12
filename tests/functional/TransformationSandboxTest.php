<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DatadirTests\DatadirTestSpecification;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\StorageApi\Options\FileUploadOptions;
use Keboola\StorageApi\Options\ListFilesOptions;

class TransformationSandboxTest extends BaseDatadirTest
{
    /**
     * @var Components
     */
    private $components;

    public function setup(): void
    {
        parent::setup();
        $files = $this->client->listFiles((new ListFilesOptions())->setTags(['my-file']));
        foreach ($files as $file) {
            $this->client->deleteFile($file['id']);
        }
    }

    private function createConfiguration(array $data): array
    {
        $this->components = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setName('data-loader-test');
        $configuration->setComponentId('transformation');
        $configId = $this->components->addConfiguration($configuration)['id'];
        $configuration->setConfigurationId($configId);
        $configurationRow = new ConfigurationRow($configuration);
        $configurationRow->setConfiguration($data);
        $configurationRow->setName('test-row');
        $rowId = $this->components->addConfigurationRow($configurationRow)['id'];
        return ['configId' => $configId, 'rowId' => $rowId];
    }

    public function testTables(): void
    {
        $configuration = [
            'backend' => 'docker',
            'description' => 'Test configuration',
            'type' => 'r',
            'input' => [
                [
                    'source' => 'in.c-main.source',
                    'destination' => 'destination.csv',
                    'whereColumn' => '',
                    'changedSince' => '',
                ],
                [
                    'source' => 'in.c-main.source',
                    'destination' => 'filtered.csv',
                    'whereColumn' => 'id',
                    'whereOperator' => 'eq',
                    'whereValues' => ['68640847'],
                    'changedSince' => '',
                ],
            ],
            'output' => [
                [
                    'destination' => 'out.c-main.r-transpose',
                    'source' => 'transpose.csv',
                ],
            ],
            'packages' => [],
            'tags' => [],
            'queries' => [
                'this is some script\ncode on multiple lines',
            ],
        ];

        $vars = $this->createConfiguration($configuration);
        $envs = [
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
            'KBC_CONFIG_ID' => $vars['configId'],
            'KBC_ROW_ID' => $vars['rowId'],
            'KBC_CONFIG_VERSION' => '2',
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/transformation-sandbox-tables/source/data',
            0,
            null,
            '',
            __DIR__ . '/transformation-sandbox-tables/expected/data/in'
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
                ' app-logger.INFO: Reading configuration ' . $vars['configId'] . ', row: ' . $vars['rowId'] . '  ',
                ' app-logger.INFO: Loaded transformation script (size 43).  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 2 table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
        $this->components->deleteConfiguration('transformation', $vars['configId']);
    }

    public function testFiles(): void
    {
        $configuration = [
            'backend' => 'docker',
            'description' => 'Test configuration',
            'type' => 'r',
            'input' => [],
            'output' => [
                [
                    'destination' => 'out.c-main.r-transpose',
                    'source' => 'transpose.csv',
                ],
            ],
            'packages' => [],
            'tags' => ['my-file'],
            'queries' => [
                'this is some script\ncode on multiple lines',
            ],
        ];

        $fileUploadOptions = new FileUploadOptions();
        $fileUploadOptions->setTags(['my-file']);
        $fileId = $this->client->uploadFile(__DIR__ . '/files/dummy', $fileUploadOptions);
        $vars = $this->createConfiguration($configuration);
        $envs = [
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
            'KBC_CONFIG_ID' => $vars['configId'],
            'KBC_ROW_ID' => $vars['rowId'],
            'KBC_CONFIG_VERSION' => '2',
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/transformation-sandbox-files/source/data',
            0,
            null,
            '',
            __DIR__ . '/transformation-sandbox-files/expected/data/in'
        );
        $targetFile = __DIR__ . '/transformation-sandbox-files/expected/data/in/files/' . $fileId . '_dummy';
        file_put_contents(
            $targetFile,
            'content'
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
                ' app-logger.INFO: Reading configuration ' . $vars['configId'] . ', row: ' . $vars['rowId'] . '  ',
                ' app-logger.INFO: Loading files with tags array (   0 => \'my-file\', )  ',
                ' app-logger.INFO: Loaded transformation script (size 43).  ',
                ' app-logger.INFO: Fetching file dummy (' . $fileId . ').  ',
                ' app-logger.INFO: Fetched file dummy (' . $fileId . ').  ',
                ' app-logger.INFO: All files were fetched.  ',
                ' app-logger.INFO: There are no input tables.  ',
                '',
            ]),
            $output
        );
        $this->components->deleteConfiguration('transformation', $vars['configId']);
        unlink($targetFile);
    }

    public function testNonExistent(): void
    {
        $envs = [
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
            'KBC_CONFIG_ID' => '123',
            'KBC_ROW_ID' => '345',
            'KBC_CONFIG_VERSION' => '2',
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/transformation-sandbox-files/source/data',
            171,
            null,
            '',
            __DIR__ . '/transformation-sandbox-files/expected/data/in'
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
                ' app-logger.INFO: Reading configuration 123, row: 345  ',
                ' app-logger.ERROR: Failed to get configuration: Configuration 123 not found  ',
                '',
            ]),
            $output
        );
    }

    public function testInvalid(): void
    {
        $configuration = ['a' => 'b'];
        $vars = $this->createConfiguration($configuration);
        $envs = [
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
            'KBC_CONFIG_ID' => $vars['configId'],
            'KBC_ROW_ID' => $vars['rowId'],
            'KBC_CONFIG_VERSION' => '2',
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/transformation-sandbox-files/source/data',
            171,
            null,
            '',
            __DIR__ . '/transformation-sandbox-files/expected/data/in'
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
                ' app-logger.INFO: Reading configuration ' . $vars['configId'] . ', row: ' . $vars['rowId'] . '  ',
                ' app-logger.ERROR: Configuration is invalid: The child node "backend" at path ' .
                    '"configuration.configuration" must be configured.  ',
                '',
            ]),
            $output
        );
        $this->components->deleteConfiguration('transformation', $vars['configId']);
    }

}
