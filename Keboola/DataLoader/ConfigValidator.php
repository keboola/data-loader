<?php

namespace Keboola\DataLoader;

use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Processor;

class ConfigValidator
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var string
     */
    private $dataDir;

    /**
     * @var array
     */
    private $input;

    /**
     * @var string
     */
    private $script;
    private $type;

    private function validateClient()
    {
        $token = getenv('KBC_TOKEN');
        if (empty($token)) {
            throw new InvalidInputException("Environment KBC_TOKEN is empty.");
        }
        $options = ['token' => $token];
        if (getenv('KBC_STORAGEAPI_URL')) {
            $options['url'] = getenv('KBC_STORAGEAPI_URL');
        }
        $this->client = new Client($options);

        $runId = getenv('KBC_RUNID');
        if (empty($runId)) {
            $this->runId = $this->client->generateRunId();
        } else {
            $this->runId = $this->client->generateRunId($runId);
        }
        $this->client->setRunId($runId);
    }

    private function validateDataDir()
    {
        $dataDir = getenv('KBC_DATADIR');
        if (empty($dataDir)) {
            $dataDir = '/data/';
        }
        $this->dataDir = $dataDir;
    }

    private function validateExportConfig()
    {
        $config = getenv('KBC_EXPORT_CONFIG');
        if (empty($config)) {
            throw new InvalidInputException("Environment KBC_EXPORT_CONFIG is empty.");
        }
        $configData = json_decode($config, true);
        if (empty($configData) || (json_last_error() != JSON_ERROR_NONE)) {
            throw new InvalidInputException("Input configuration is invalid: " . json_last_error_msg());
        }
        $processor = new Processor();
        $configData = $processor->processConfiguration(new ExportConfig(), ['configuration' => $configData]);
        $this->input = $configData['storage']['input'];
    }

    private function validateConfig()
    {
        $configId = getenv('KBC_CONFIG_ID');
        $rowId = getenv('KBC_ROW_ID');
        $versionId = getenv('KBC_ROW_VERSION');
        if (empty($configId) || empty($rowId) || empty($versionId)) {
            throw new InvalidInputException("Environment KBC_CONFIG_ID or KBC_ROW_ID or KBC_ROW_VERSION is empty.");
        }
        $component = new Components($this->client);
        $configData = $component->getConfigurationRowVersion('transformation', $configId, $rowId, $versionId);
        $processor = new Processor();
        $configData = $processor->processConfiguration(new TransformationConfig(), ['configuration' => $configData]);
        if ($configData['configuration']['backend'] != 'docker') {
            throw new InvalidInputException(
                "Invalid transformation configuration backend: " . $configData['configuration']['backend']
            );
        }
        $this->type = $configData['configuration']['type'];
        $this->input['tables'] = $configData['configuration']['input'];
        $this->script = implode("\n", $configData['configuration']['queries']);
    }

    public function validate(Logger $logger)
    {
        $this->validateClient();

        $handler = new StorageApiHandler('data-loader', $this->client);
        $logger->pushHandler($handler);
        $logger->info("DataLoader is loading data", ['runId' => $this->runId]);
        $this->validateDataDir();
        if (empty(getenv('KBC_CONFIG_ID'))) { // for fwd compat
            $this->validateExportConfig();
        }
        if (empty(getenv('KBC_EXPORT_CONFIG'))) { // for bwd compat
            $this->validateConfig();
        }
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getDataDir()
    {
        return $this->dataDir;
    }

    /**
     * @return string
     */
    public function getRunId()
    {
        return $this->runId;
    }

    /**
     * @return array
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return string
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        switch ($this->type) {
            case 'r':
                return 'R';
            case 'python':
                return 'py';
            default:
                throw new InvalidInputException('Invalid transformation type ' . $this->type);
        }
    }
}
