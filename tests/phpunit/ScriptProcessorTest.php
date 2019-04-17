<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\ExportConfig;
use Keboola\DataLoader\ScriptProcessor;
use Keboola\DataLoader\TransformationConfig;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ScriptProcessorTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Temp
     */
    private $temp;

    public function setUp()
    {
        if (empty(getenv('KBC_TEST_TOKEN')) || empty(getenv('KBC_TEST_URL'))) {
            self::fail('KBC_TEST_TOKEN and KBC_TEST_URL environment variables must be set.');
        }
        $this->client = new Client([
            'token' => getenv('KBC_TEST_TOKEN'),
            'url' => getenv('KBC_TEST_URL'),
        ]);
        $this->temp = new Temp('data-loader');
        $this->temp->initRunFolder();
        parent::setUp();
    }

    public function testPythonScriptEmpty(): void
    {
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($this->temp->getTmpFolder(), 'python', '');
        self::assertFileExists($this->temp->getTmpFolder() . '/main.py');
        self::assertEquals('', file_get_contents($this->temp->getTmpFolder() . '/main.py'));
    }

    public function testPythonScriptNotEmpty(): void
    {
        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', 'Achoo');
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the first cell for token'],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the second cell'],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['Achoo'],
                ],
            ],
            $data['cells']
        );
    }
}
