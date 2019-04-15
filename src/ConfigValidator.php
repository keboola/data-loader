<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Components;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigValidator
{
    public const INTERNAL_ERROR = 170;
    public const CONFIGURATION_INVALID = 171;
    public const FILES_ERROR = 172;
    public const TABLES_ERROR = 173;
    public const INTERNAL_II_ERROR = 174;
    public const INTERNAL_CLIENT_ERROR = 175;
    public const FILES_CLIENT_ERROR = 176;
    public const TABLES_CLIENT_ERROR = 177;

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
    private $input = [];

    /**
     * @var string
     */
    private $script = '';

    /**
     * @var string
     */
    private $type;

    /**
     * @var Logger
     */
    private $logger;

    private function validateClient(): void
    {
        $token = getenv('KBC_TOKEN');
        if (empty($token)) {
            throw new InvalidInputException('Environment KBC_TOKEN is empty.', self::INTERNAL_ERROR);
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

    private function validateDataDir(): void
    {
        $dataDir = getenv('KBC_DATADIR');
        if (empty($dataDir)) {
            $dataDir = '/data/';
        }
        $this->dataDir = $dataDir;
    }

    private function validateExportConfig(): void
    {
        $this->logger->info('Loading configuration from EXPORT_CONFIG');
        $config = getenv('KBC_EXPORT_CONFIG');
        if (empty($config)) {
            throw new InvalidInputException('Environment KBC_EXPORT_CONFIG is empty.', self::INTERNAL_ERROR);
        }
        $configData = json_decode($config, true);
        if (empty($configData) || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new InvalidInputException('Input configuration is invalid: ' . json_last_error_msg());
        }
        $processor = new Processor();
        try {
            $configData = $processor->processConfiguration(new ExportConfig(), ['configuration' => $configData]);
        } catch (InvalidConfigurationException $e) {
            throw new InvalidInputException(
                'Configuration is invalid: ' . $e->getMessage(),
                self::CONFIGURATION_INVALID
            );
        }
        $this->input = $configData['storage']['input'];
    }

    private function validateConfig(): void
    {
        $configId = getenv('KBC_CONFIG_ID');
        $versionId = getenv('KBC_CONFIG_VERSION');
        $rowId = getenv('KBC_ROW_ID');
        $this->logger->info('Reading configuration ' . $configId . ', row: ' . $rowId);
        if (empty($configId) || empty($rowId) || empty($versionId)) {
            throw new InvalidInputException(
                'Environment KBC_CONFIG_ID or KBC_ROW_ID or KBC_CONFIG_VERSION is empty.',
                self::INTERNAL_ERROR
            );
        }
        $component = new Components($this->client);
        try {
            $configData = $component->getConfigurationVersion('transformation', $configId, $versionId);
            $configData['rows'] = $configData['rows'] ?? [];
            foreach ($configData['rows'] as $row) {
                if ($row['id'] === $rowId) {
                    $rowData = $row;
                    break;
                }
            }
        } catch (ClientException $e) {
            throw new InvalidInputException(
                'Failed to get configuration: ' . $e->getMessage(),
                self::CONFIGURATION_INVALID
            );
        }
        if (empty($rowData)) {
            throw new InvalidInputException('Configuration Row not found.', self::CONFIGURATION_INVALID);
        }
        $processor = new Processor();
        try {
            $rowData = $processor->processConfiguration(new TransformationConfig(), ['configuration' => $rowData]);
        } catch (InvalidConfigurationException $e) {
            throw new InvalidInputException(
                'Configuration is invalid: ' . $e->getMessage(),
                self::CONFIGURATION_INVALID
            );
        }
        $this->type = $rowData['configuration']['type'];
        $this->input['tables'] = $rowData['configuration']['input'];
        foreach ($this->input['tables'] as &$table) {
            if (count($table['whereValues']) > 0) {
                $table['where_values'] = $table['whereValues'];
            }
            if (!empty($table['whereColumn'])) {
                $table['where_column'] = $table['whereColumn'];
            }
            if (!empty($table['whereOperator'])) {
                $table['where_operator'] = $table['whereOperator'];
            }
            if (!empty($table['changedSince'])) {
                $table['changed_since'] = $table['changedSince'];
            }
            unset($table['whereValues']);
            unset($table['whereColumn']);
            unset($table['whereOperator']);
            unset($table['changedSince']);
        }
        if (!empty($rowData['configuration']['tags'])) {
            $this->input['files'][0]['tags'] = $rowData['configuration']['tags'];
            $this->logger->info('Loading files with tags ' . var_export($this->input['files'][0]['tags'], true));
        }
        $this->script = implode('\n', $rowData['configuration']['queries']);
        $this->logger->info(sprintf('Loaded transformation script (size %s).', strlen($this->script)));
    }

    public function validate(Logger $logger): void
    {
        $this->validateClient();

        $handler = new StorageApiHandler('data-loader', $this->client);
        $logger->pushHandler($handler);
        $logger->info('DataLoader is loading data', ['runId' => $this->runId]);
        $this->logger = $logger;
        $this->validateDataDir();
        if (empty(getenv('KBC_CONFIG_ID'))) { // for fwd compat
            $this->validateExportConfig();
        }
        if (empty(getenv('KBC_EXPORT_CONFIG'))) { // for bwd compat
            $this->validateConfig();
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getInput(): array
    {
        return $this->input;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getExtension(): string
    {
        switch ($this->type) {
            case 'r':
                return 'R';
            case 'python':
                return 'py';
            default:
                throw new InvalidInputException(
                    sprintf('Invalid transformation type "%s".', $this->type),
                    self::CONFIGURATION_INVALID
                );
        }
    }
}
