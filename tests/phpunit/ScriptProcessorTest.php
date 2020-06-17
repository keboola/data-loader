<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\ScriptProcessor;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Options\FileUploadOptions;
use Keboola\StorageApi\Options\ListFilesOptions;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

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

    public function setUp(): void
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

        $options = new ListFilesOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG, ScriptProcessor::R_SANDBOX_TEMPLATE_TAG]);
        foreach ($this->client->listFiles($options) as $file) {
            $this->client->deleteFile($file['id']);
        }
        // wait for Storage to synchronize file changes
        sleep(1);

        parent::setUp();
    }

    public function testRNoScriptNoTemplate(): void
    {
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($this->temp->getTmpFolder(), 'r', '');
        self::assertFileExists($this->temp->getTmpFolder() . '/main.R');
        self::assertEquals(' ', file_get_contents($this->temp->getTmpFolder() . '/main.R'));
    }

    public function testREmptyScriptNoTemplate(): void
    {
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($this->temp->getTmpFolder(), 'r', ' ');
        self::assertFileExists($this->temp->getTmpFolder() . '/main.R');
        self::assertEquals('', file_get_contents($this->temp->getTmpFolder() . '/main.R'));
    }

    public function testPythonScriptNoTemplate(): void
    {
        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', 'some script');
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ["some script\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testPythonNoScriptNoTemplate(): void
    {
        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', '');
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals([], $data['cells']);
    }

    public function testNoScriptProjectTemplate(): void
    {
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-project.ipynb', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', '');
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the first cell for project', 'on a new line'],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the second cell'],
                ],
            ],
            $data['cells']
        );
    }

    public function testPythonScriptProjectTemplate(): void
    {
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-project.ipynb', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', "Achoo\nAchordata");
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the first cell for project', 'on a new line'],
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
                    'source' => ["Achoo\n", "Achordata\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testPythonScriptUserTemplate(): void
    {
        $tokenInfo = $this->client->verifyToken();
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG, $tokenInfo['description']]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-user.ipynb', $options);
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-project.ipynb', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

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
                    'source' => ['this is the first cell for user'],
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
                    'source' => ["Achoo\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testChunkedPythonScriptNoTemplate(): void
    {
        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', null,  ["the first chunk\nwith a second line", 'the second chunk']);
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ["the first chunk\n", "with a second line\n"],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ["the second chunk\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testChunkedPythonScriptProjectTemplate(): void
    {
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-project.ipynb', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', null,  ["the first chunk\nwith a second line", 'the second chunk']);
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the first cell for project', 'on a new line'],
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
                    'source' => ["the first chunk\n", "with a second line\n"],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ["the second chunk\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testChunkedPythonScriptUserTemplate(): void
    {
        $tokenInfo = $this->client->verifyToken();
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG, $tokenInfo['description']]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-user.ipynb', $options);
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::PYTHON_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-notebook-project.ipynb', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'python', null,  ["the first chunkwith\nwith a second line", 'the second chunk']);
        self::assertFileExists($dir . 'notebook.ipynb');
        $data = json_decode(file_get_contents($dir . 'notebook.ipynb'), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals(
            [
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ['this is the first cell for user'],
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
                    'source' => ["the first chunk\n", "with a second line\n"],
                ],
                [
                    'cell_type' => 'code',
                    'execution_count' => null,
                    'metadata' => [],
                    'outputs' => [],
                    'source' => ["the second chunk\n"],
                ],
            ],
            $data['cells']
        );
    }

    public function testRScriptProjectTemplate(): void
    {
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::R_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-project.R', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'r', "Achoo\nAchordata");
        self::assertFileExists($dir . 'main.R');
        $data = file_get_contents($dir . 'main.R');
        self::assertEquals(
            "some project data\non a new line\n\n\nAchoo\nAchordata",
            $data
        );
    }

    public function testRScriptUserTemplate(): void
    {
        $tokenInfo = $this->client->verifyToken();
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::R_SANDBOX_TEMPLATE_TAG, $tokenInfo['description']]);
        $this->client->uploadFile(__DIR__ . '/data/sample-user.R', $options);
        $options = new FileUploadOptions();
        $options->setTags([ScriptProcessor::R_SANDBOX_TEMPLATE_TAG]);
        $this->client->uploadFile(__DIR__ . '/data/sample-project.R', $options);
        // wait for Storage to synchronize file changes
        sleep(1);

        $dir = $this->temp->getTmpFolder() . '/';
        $processor = new ScriptProcessor($this->client, new TestLogger());
        $processor->processScript($dir, 'r', 'Achoo');
        self::assertFileExists($dir . 'main.R');
        $data = file_get_contents($dir . 'main.R');
        self::assertEquals(
            "some user data\n\n\nAchoo",
            $data
        );
    }
}
