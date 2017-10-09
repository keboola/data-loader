<?php

require "vendor/autoload.php";

use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\InputMapping\Reader\Reader;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$log = new Logger('app-logger');
$log->pushHandler(new StreamHandler('php://stdout'));
$log->info("Starting Data Loader");

try {
    $validator = new \Keboola\DataLoader\ConfigValidator();
    $validator->validate($log);
    $runId = $validator->getRunId();

    $reader = new Reader($validator->getClient(), $log);
    $fs = new \Symfony\Component\Filesystem\Filesystem();
    $fs->mkdir($validator->getDataDir() . '/in/tables/');
    $fs->mkdir($validator->getDataDir() . '/in/files/');
    $fs->mkdir($validator->getDataDir() . '/out/tables/');
    $fs->mkdir($validator->getDataDir() . '/out/files/');
    if ($validator->getScript()) {
        file_put_contents($validator->getDataDir() . 'main.' . $validator->getExtension(), $validator->getScript());
    } else {
        $log->info("Script is empty.", ['runId' => $runId]);
    }
    if (!empty($validator->getInput()['files'])) {
        try {
            $reader->downloadFiles($validator->getInput()['files'], $validator->getDataDir() . '/in/files/');
        } catch (InvalidInputException $e) {
            throw new InvalidInputException($e->getMessage(), \Keboola\DataLoader\ConfigValidator::FILES_ERROR, $e);
        }
    } else {
        $log->info("Input files empty.", ['runId' => $runId]);
    }
    if (!empty($validator->getInput()['tables'])) {
        try {
            $reader->downloadTables($validator->getInput()['tables'], $validator->getDataDir() . '/in/tables/');
        } catch (InvalidInputException $e) {
            throw new InvalidInputException($e, \Keboola\DataLoader\ConfigValidator::TABLES_ERROR, $e);
        }
    } else {
        $log->info("Input tables empty.", ['runId' => $runId]);
    }
} catch (InvalidInputException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    if ($e->getCode()) {
        exit($e->getCode());
    } else {
        exit(\Keboola\DataLoader\ConfigValidator::INTERNAL_II_ERROR);
    }
} catch (\Keboola\StorageApi\Exception $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(\Keboola\DataLoader\ConfigValidator::INTERNAL_CLIENT_ERROR);
} catch (\Exception $e) {
    $log->critical($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(2);
}
