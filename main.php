<?php

require "vendor/autoload.php";

use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\InputMapping\Reader\Reader;
use Keboola\StorageApi\Client;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

$log = new Logger('name');
$log->pushHandler(new SyslogHandler('data-loader'));
$log->info("Starting Data Loader");

try {
    $config = getenv('KBC_EXPORT_CONFIG');
    if (empty($config)) {
        throw new InvalidInputException("Environment KBC_EXPORT_CONFIG is empty.");
    }
    $configData = json_decode($config, true);
    if (empty($configData) || (json_last_error() != JSON_ERROR_NONE)) {
        throw new InvalidInputException("Input configuration is invalid: " . json_last_error_msg());
    }

    $token = getenv('KBC_TOKEN');
    if (empty($token)) {
        throw new InvalidInputException("Environment KBC_TOKEN is empty.");
    }
    $dataDir = getenv('KBC_DATADIR');
    if (empty($dataDir)) {
        $dataDir = '/data/';
    }

    $client = new Client(['token' => $token]);
    $reader = new Reader($client);
    $reader->downloadFiles($configData['storage']['input']['files'], $dataDir);
    $reader->downloadTables($configData['storage']['input']['tables'], $dataDir);
} catch (InvalidInputException $e) {
    $log->error($e->getMessage(), ['exception' => $e]);
} catch (\Exception $e) {
    $log->critical($e->getMessage(), ['exception' => $e]);
}
