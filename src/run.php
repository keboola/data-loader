<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Keboola\DataLoader\ConfigValidator;
use Keboola\DataLoader\ScriptProcessor;
use Keboola\DataLoader\WorkspaceProvider;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\InputMapping\Reader\NullWorkspaceProvider;
use Keboola\InputMapping\Reader\Options\InputTableOptionsList;
use Keboola\InputMapping\Reader\Reader;
use Keboola\InputMapping\Reader\State\InputTableStateList;
use Keboola\StorageApi\ClientException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;

error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext): bool {
    if (!(error_reporting() & $errno)) {
        // respect error_reporting() level
        // libraries used in custom components may emit notices that cannot be fixed
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$log = new Logger('app-logger');
$log->pushHandler(new StreamHandler('php://stdout'));
$log->info('Starting Data Loader');

try {
    $validator = new ConfigValidator();
    $validator->validate($log);
    $runId = $validator->getRunId();

    $workspaceProvider = $validator->getWorkspaceId()
        ? new WorkspaceProvider(
            (string) $validator->getWorkspaceId(),
            (string) $validator->getWorkspacePassword(),
            $validator->getClient()
        )
        : new NullWorkspaceProvider();
    $reader = new Reader($validator->getClient(), $log, $workspaceProvider);
    $fs = new Filesystem();
    $fs->mkdir($validator->getDataDir() . '/in/tables/');
    $fs->mkdir($validator->getDataDir() . '/in/files/');
    $fs->mkdir($validator->getDataDir() . '/out/tables/');
    $fs->mkdir($validator->getDataDir() . '/out/files/');
    $scriptProcessor = new ScriptProcessor($validator->getClient(), $log);
    try {
        $scriptProcessor->processScript(
            $validator->getDataDir(),
            $validator->getType(),
            $validator->getScript(),
            $validator->getCodeChunks()
        );
    } catch (ClientException $e) {
        throw new InvalidInputException($e->getMessage(), ConfigValidator::SCRIPT_CLIENT_ERROR, $e);
    }
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
            $stage = Reader::STAGING_LOCAL;
            if ($workspaceProvider instanceof WorkspaceProvider) {
                $stage = $workspaceProvider->getWorkspaceStagingName();
            }
            $reader->downloadTables(
                new InputTableOptionsList($validator->getInput()['tables']),
                new InputTableStateList([]),
                $validator->getDataDir() . '/in/tables/',
                $stage
            );
        } catch (InvalidInputException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::TABLES_ERROR, $e);
        } catch (ClientException $e) {
            throw new InvalidInputException($e->getMessage(), ConfigValidator::TABLES_CLIENT_ERROR, $e);
        }
    } else {
        $log->info('There are no input tables.', ['runId' => $runId]);
    }
    // make sure everything that was created has global permissions
    $fs->chmod($validator->getDataDir(), 0777, 0000, true);
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
