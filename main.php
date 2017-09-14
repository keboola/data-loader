<?php

require "vendor/autoload.php";

use Keboola\InputMapping\Exception\InvalidInputException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        file_put_contents('main.' . $validator->getExtension(), $validator->getScript());
    } else {
        $log->info("Script is empty.", ['runId' => $runId]);
    }
    if (!empty($validator->getInput()['files'])) {
        $reader->downloadFiles($validator->getInput()['files'], $validator->getDataDir() . '/in/files/');
    } else {
        $log->info("Input files empty.", ['runId' => $runId]);
    }
    if (!empty($validator->getInput()['tables'])) {
        $reader->downloadTables($validator->getInput()['tables'], $validator->getDataDir() . '/in/tables/');
    } else {
        $log->info("Input tables empty.", ['runId' => $runId]);
    }
} catch (InvalidInputException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(1);
} catch (InvalidConfigurationException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(1);
} catch (\Keboola\StorageApi\Exception $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(1);
} catch (\Exception $e) {
    $log->critical($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(2);
}
