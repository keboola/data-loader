<?php

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DatadirTests\DatadirTestSpecification;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;
use Keboola\StorageApi\Options\Components\ListConfigurationWorkspacesOptions;
use Keboola\StorageApi\Workspaces;
use Symfony\Component\Finder\Finder;

class SynapseWorkspaceTest extends BaseDatadirTest
{
    public const TEST_COMPONENT_ID = 'keboola.sandboxes';

    public const TEST_CONFIGURATION_ID = 'sandbox-test-configuration';

    public const TEST_CONFIGURATION_NAME = 'sandbox-tests';

    public function setUp(): void
    {
        if (!getenv('RUN_SYNAPSE_TESTS')) {
            return;
        }
        putenv('KBC_TEST_URL=' . getenv('KBC_TEST_URL_SYNAPSE'));
        putenv('KBC_TEST_TOKEN=' . getenv('KBC_TEST_TOKEN_SYNAPSE'));
        parent::setUp();
        $components = new Components($this->client);
        $workspaces = new Workspaces($this->client);
        $options = new ListComponentConfigurationsOptions();
        $options->setComponentId(self::TEST_COMPONENT_ID);
        foreach ($components->listComponentConfigurations($options) as $configuration) {
            echo "\ngoing to delete config " . $configuration['name'] . "\n";
            if ($configuration['name'] === self::TEST_CONFIGURATION_NAME) {
                $wOptions = new ListConfigurationWorkspacesOptions();
                $wOptions->setComponentId(self::TEST_COMPONENT_ID);
                $wOptions->setConfigurationId($configuration['id']);
                foreach ($components->listConfigurationWorkspaces($wOptions) as $workspace) {
                    $workspaces->deleteWorkspace($workspace['id']);
                }
                $components->deleteConfiguration(self::TEST_COMPONENT_ID, $configuration['id']);
            }
        }
    }

    private function createTestWorkspace(): array
    {
        $components = new Components($this->client);
        $configuration = new Configuration();
        $configuration->setComponentId(self::TEST_COMPONENT_ID);
        $configuration->setName(self::TEST_CONFIGURATION_NAME);
        $configuration->setConfigurationId(self::TEST_CONFIGURATION_NAME);
        $components->addConfiguration($configuration);
        $workspace = $components->createConfigurationWorkspace(
            self::TEST_COMPONENT_ID,
            $configuration->getConfigurationId(),
            ['backend' => 'synapse']
        );
        return $workspace;
    }

    public function testBasicPythonWithSynapse(): void
    {
        if (!getenv('RUN_SYNAPSE_TESTS')) {
            $this->markTestSkipped('Synapse Tests Disabled. To enable set RUN_SYNAPS_TESTS=1');
        }
        // create the staging workspace and put there the test data
        $workspace = $this->createTestWorkspace();
        $configuration = [
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'test-table',
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
            'WORKSPACE_ID' => $workspace['id'],
            'WORKSPACE_PASSWORD' => $workspace['connection']['password'],
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/sandbox-with-workspace/source/data',
            0,
            null,
            '',
            null
        );
        $tempDatadir = $this->getTempDatadir($specification);
        $process = $this->runScript($tempDatadir->getTmpFolder(), $envs);
        $output = $process->getOutput();

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        /* we want to check that the table exists in the workspace, so we try to load it, which fails, because of
            the _timestamp columns, but that's okay. It means that the table is indeed in the workspace. */
        try {
            $this->client->createTableAsyncDirect(
                'in.c-main',
                ['dataWorkspaceId' => $workspace['id'], 'dataTableName' => 'test-table', 'name' => 'sandboxes-test']
            );
            self::fail('Must throw exception');
        } catch (ClientException $e) {
            self::assertContains('Invalid columns: _timestamp:', $e->getMessage());
        }
        $output = preg_replace('#\[.*?\]#', '', $output);
        $output = preg_replace('#\{.*?\}#', '', $output);
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Loading configuration from EXPORT_CONFIG  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: There are no input files.  ',
                ' app-logger.INFO: Table "in.c-main.source" will be cloned.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Cloning 1 tables to snowflake workspace.  ',
                ' app-logger.INFO: Processing 1 workspace exports.  ',
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
}
