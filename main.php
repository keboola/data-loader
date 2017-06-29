<?php

require "vendor/autoload.php";

use Keboola\DataLoader\ExportConfig;
use Keboola\InputMapping\Exception\InvalidInputException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Keboola\InputMapping\Reader\Reader;
use Keboola\StorageApi\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Processor;

$log = new Logger('app-logger');
$log->pushHandler(new StreamHandler('php://stdout'));
$log->info("Starting Data Loader");

try {
    $token = getenv('KBC_TOKEN');
    if (empty($token)) {
        throw new InvalidInputException("Environment KBC_TOKEN is empty.");
    }
    $options = ['token' => $token];
    if (getenv('KBC_STORAGEAPI_URL')) {
        $options['url'] = getenv('KBC_STORAGEAPI_URL');
    }
    $client = new Client($options);

    $runId = getenv('KBC_RUNID');
    if (empty($runId)) {
        $runId = $client->generateRunId();
    } else {
        $runId = $client->generateRunId($runId);
    }
    $client->setRunId($runId);
    $handler = new \Keboola\DataLoader\StorageApiHandler('data-loader', $client);
    $log->pushHandler($handler);
    $log->info("DataLoader is loading data", ['runId' => $runId]);

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

    $dataDir = getenv('KBC_DATADIR');
    if (empty($dataDir)) {
        $dataDir = '/data/';
    }

    $reader = new Reader($client, $log);
    $fs = new \Symfony\Component\Filesystem\Filesystem();
    $fs->mkdir($dataDir . '/in/tables/');
    $fs->mkdir($dataDir . '/in/files/');
    $fs->mkdir($dataDir . '/out/tables/');
    $fs->mkdir($dataDir . '/out/files/');
    if (!empty($configData['storage']['input']['files'])) {
        $reader->downloadFiles($configData['storage']['input']['files'], $dataDir . '/in/files/');
    } else {
        $log->info("Input files empty.", ['runId' => $runId]);
    }
    if (!empty($configData['storage']['input']['tables'])) {
        $reader->downloadTables($configData['storage']['input']['tables'], $dataDir . '/in/tables/');
    } else {
        $log->info("Input tables empty.", ['runId' => $runId]);
    }
} catch (InvalidInputException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(1);
} catch (InvalidConfigurationException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(1);
} catch (\Exception $e) {
    $log->critical($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(2);
}
