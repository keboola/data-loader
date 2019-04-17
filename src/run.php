<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Keboola\DataLoader\ConfigValidator;
use Keboola\DataLoader\ScriptProcessor;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\InputMapping\Reader\Options\InputTableOptionsList;
use Keboola\InputMapping\Reader\Reader;
use Keboola\InputMapping\Reader\State\InputTableStateList;
use Keboola\StorageApi\ClientException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;

$log = new Logger('app-logger');
$log->pushHandler(new StreamHandler('php://stdout'));
$log->info('Starting Data Loader');

try {
    $validator = new ConfigValidator();
    $validator->validate($log);
    $runId = $validator->getRunId();

    $reader = new Reader($validator->getClient(), $log);
    $fs = new Filesystem();
    $fs->mkdir($validator->getDataDir() . '/in/tables/');
    $fs->mkdir($validator->getDataDir() . '/in/files/');
    $fs->mkdir($validator->getDataDir() . '/out/tables/');
    $fs->mkdir($validator->getDataDir() . '/out/files/');
    $scriptProcessor = new ScriptProcessor($validator->getClient(), $log);
    $scriptProcessor->processScript($validator->getDataDir(), $validator->getType(), $validator->getScript());
    if (!empty($validator->getInput()['files'])) {
        try {
            $reader->downloadFiles($validator->getInput()['files'], $validator->getDataDir() . '/in/files/');
        } catch (InvalidInputException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::FILES_ERROR, $e);
        } catch (ClientException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::FILES_CLIENT_ERROR, $e);
        }
    } else {
        $log->info('There are no input files.', ['runId' => $runId]);
    }
    if (!empty($validator->getInput()['tables'])) {
        try {
            $reader->downloadTables(
                new InputTableOptionsList($validator->getInput()['tables']),
                new InputTableStateList([]),
                $validator->getDataDir() . '/in/tables/'
            );
        } catch (InvalidInputException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::TABLES_ERROR, $e);
        } catch (ClientException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::TABLES_CLIENT_ERROR, $e);
        }
    } else {
        $log->info('There are no input tables.', ['runId' => $runId]);
    }
} catch (InvalidInputException $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    if ($e->getCode()) {
        exit($e->getCode());
    } else {
        exit(ConfigValidator::INTERNAL_II_ERROR);
    }
} catch (\Keboola\StorageApi\Exception $e) {
    $log->error($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(ConfigValidator::INTERNAL_CLIENT_ERROR);
} catch (Throwable $e) {
    $log->critical($e->getMessage(), ['exception' => $e, 'runId' => isset($runId) ? $runId : 'N/A']);
    exit(2);
}
