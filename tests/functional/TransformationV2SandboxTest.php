<?php


namespace Keboola\DataLoader\FunctionalTests;


use Keboola\DatadirTests\DatadirTestSpecification;
use Keboola\DataLoader\ScriptProcessor;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;
use Keboola\StorageApi\Options\FileUploadOptions;
use Keboola\StorageApi\Options\ListFilesOptions;

class TransformationV2SandboxTest extends BaseDatadirTest
{
    public const COMPONENT_ID = 'keboola.python-transformation-v2';

    /**
     * @var Components
     */
    private $components;

    public function setup(): void
    {
        parent::setup();
        $options = new ListFilesOptions();
        $options->setTags(['my-file', ScriptProcessor::R_SANDBOX_TEMPLATE_TAG]);
        $files = $this->client->listFiles($options);
        foreach ($files as $file) {
            $this->client->deleteFile($file['id']);
        }
        $this->components = new Components($this->client);
        $options = new ListComponentConfigurationsOptions();
        $options->setComponentId(self::COMPONENT_ID);
        $configs = $this->components->listComponentConfigurations($options);
        foreach ($configs as $config) {
            $this->components->deleteConfiguration(self::COMPONENT_ID, $config['id']);
        }
    }

    private function createConfiguration(): array
    {
        $v2ComponentId = self::COMPONENT_ID;
        $configuration = new Configuration();
        $configuration->setComponentId($v2ComponentId);
        $configuration->setName('data-loader-test');
        $configuration->setConfiguration([
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.source',
                            'destination' => 'filtered.csv',
                            'changed_since' => null,
                            'where_column' => 'id',
                            'where_values' => ['68640847'],
                            'where_operator' => 'eq',
                            'limit' => 10,
                        ],
                    ],
                    'files' => [
                        [
                            'tags' => ['my-file'],
                        ],
                    ],
                ],
            ],
            "parameters" => [
                "packages" => ["numpy"],
                "blocks" => [
                    [
                        "name" => "a block",
                        "codes" => [
                            [
                                "name" => "hello",
                                "script" => ["print(\"hello\")"]
                            ],[
                                "name" => "bye",
                                "script" => ["print(\"bye\")"]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $componentsClient = new Components($this->client);
        $configuration = $componentsClient->addConfiguration($configuration);
        return $configuration;
    }

    public function testFullTransformationV2Config(): void
    {
        $configuration = $this->createConfiguration();
        // upload the test file
        $fileUploadOptions = new FileUploadOptions();
        $fileUploadOptions->setTags(['my-file']);
        $fileId = $this->client->uploadFile(__DIR__ . '/files/dummy', $fileUploadOptions);

        $envs = [
            'KBC_TOKEN' => getenv('KBC_TEST_TOKEN'),
            'KBC_STORAGEAPI_URL' => getenv('KBC_TEST_URL'),
            'KBC_COMPONENT_ID' => self::COMPONENT_ID,
            'KBC_CONFIG_ID' => $configuration['id'],
            'KBC_CONFIG_VERSION' => $configuration['version'],
        ];
        $specification = new DatadirTestSpecification(
            __DIR__ . '/transformationV2-fullConfig/source/data',
            0,
            null,
            '',
            __DIR__ . '/transformationV2-fullConfig/expected/data/in'
        );
        $targetFile = __DIR__ . '/transformationV2-fullConfig/expected/data/in/files/' . $fileId . '_dummy';
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
        var_dump($output);
        var_dump($process->getErrorOutput());
        self::assertEquals(
            implode("\n", [
                ' app-logger.INFO: Starting Data Loader  ',
                ' app-logger.INFO: DataLoader is loading data  ',
                ' app-logger.INFO: Reading ' . self::COMPONENT_ID . ' configuration ' . $configuration['id'] . '  ',
                ' app-logger.INFO: Loaded transformation script (size 26).  ',
                ' app-logger.INFO: Found no user-defined template, using built-in.  ',
                ' app-logger.INFO: Fetching file dummy (' . $fileId . ').  ',
                ' app-logger.INFO: Fetched file dummy (' . $fileId . ').  ',
                ' app-logger.INFO: All files were fetched.  ',
                ' app-logger.INFO: Fetched table in.c-main.source.  ',
                ' app-logger.INFO: Processing 1 local table exports.  ',
                ' app-logger.INFO: All tables were fetched.  ',
                '',
            ]),
            $output
        );
    }
}