<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\Csv\CsvFile;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\Exception\DatadirTestsException;
use Keboola\StorageApi\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class BaseDatadirTest extends AbstractDatadirTestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setup(): void
    {
        parent::setUp();
        if (empty(getenv('KBC_TEST_TOKEN')) || empty(getenv('KBC_TEST_URL'))) {
            self::fail('KBC_TEST_TOKEN and KBC_TEST_URL environment variables must be set.');
        }
        $this->client = new Client(['token' => getenv('KBC_TEST_TOKEN'), 'url' => getenv('KBC_TEST_URL')]);
        $this->client->dropBucket('in.c-main', ['force' => true]);
        $this->client->createBucket('main', 'in');
        $this->client->createTable('in.c-main', 'source', new CsvFile(__DIR__ . '/files/source.csv'));
    }

    protected function runScript(string $datadirPath, array $envs = []): Process
    {
        // override base method to pass environment
        $fs = new Filesystem();

        $script = $this->getScript();
        if (!$fs->exists($script)) {
            throw new DatadirTestsException(sprintf(
                'Cannot open script file "%s"',
                $script
            ));
        }

        $runCommand = [
            'php',
            $script,
        ];
        $runProcess = new Process($runCommand);
        $envs = array_merge(['KBC_DATADIR' => $datadirPath], $envs);
        $runProcess->setEnv($envs);
        $runProcess->setTimeout(0);
        $runProcess->run();
        return $runProcess;
    }

    protected function assertMatchesSpecification(
        DatadirTestSpecificationInterface $specification,
        Process $runProcess,
        string $tempDatadir
    ): void {
        // override base method to compare /in directory instead of /out directory and not to compare the manifest file
        if ($specification->getExpectedReturnCode() !== null) {
            $this->assertProcessReturnCode($specification->getExpectedReturnCode(), $runProcess);
        } else {
            $this->assertNotSame(0, $runProcess->getExitCode(), 'Exit code should have been non-zero');
        }
        if ($specification->getExpectedStdout() !== null) {
            $this->assertSame(
                $specification->getExpectedStdout(),
                $runProcess->getOutput(),
                'Failed asserting stdout output'
            );
        }
        if ($specification->getExpectedStderr() !== null) {
            $this->assertSame(
                $specification->getExpectedStderr(),
                $runProcess->getErrorOutput(),
                'Failed asserting stderr output'
            );
        }
        if ($specification->getExpectedOutDirectory() !== null) {
            $finder = new Finder();
            $manifests = iterator_to_array($finder->files()->in($tempDatadir . '/in/tables')->name('*.manifest'));
            $finder = new Finder();
            $csv = iterator_to_array($finder->files()->in($tempDatadir . '/in/tables')->notName('*.manifest'));
            self::assertEquals(count($csv), count($manifests));
            $finder = new Finder();
            $manifests = iterator_to_array($finder->files()->in($tempDatadir . '/in/files')->name('*.manifest'));
            $finder = new Finder();
            $csv = iterator_to_array($finder->files()->in($tempDatadir . '/in/files')->notName('*.manifest'));
            self::assertEquals(count($csv), count($manifests));
            foreach ($manifests as $manifest) {
                /** @var SplFileInfo $manifest */
                unlink($manifest->getPathname());
            }
            $this->assertDirectoryContentsSame(
                $specification->getExpectedOutDirectory(),
                $tempDatadir . '/in'
            );
        }
    }
}
