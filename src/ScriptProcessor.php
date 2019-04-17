<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use _HumbugBox01ece8fd5bed\Symfony\Component\Console\Exception\LogicException;
use Aws\S3\S3Client;
use Keboola\InputMapping\Configuration\File;
use Keboola\InputMapping\Configuration\Table;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Options\GetFileOptions;
use Keboola\StorageApi\Options\ListFilesOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ScriptProcessor
{
    const R_SANDBOX_TEMPLATE_TAG = '_r_sandbox_template_';
    const PYTHON_SANDBOX_TEMPLATE_TAG = '_python_sandbox_template_';
    const R_SANDBOX_TYPE = 'r';
    const PYTHON_SANDBOX_TYPE = 'python';
    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public static function getSandboxTags(): array
    {
        return [
            'r' => '_r_sandbox_template_',
            'python' => '_python_sandbox_template_',
        ];
    }

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    private function getTagByType(string $type): string
    {
        switch ($type) {
            case self::R_SANDBOX_TYPE:
                return self::R_SANDBOX_TEMPLATE_TAG;
            case self::PYTHON_SANDBOX_TYPE:
                return self::PYTHON_SANDBOX_TEMPLATE_TAG;
        }
    }

    private function getProjectTemplateId(string $type): ?string
    {
        $options = new ListFilesOptions();
        $options->setTags([self::getSandboxTags()[$type]]);
        $options->setLimit(1);
        $files = $this->client->listFiles($options);
        if ($files) {
            return $files[0]['id'];
        }
        return null;
    }

    private function getUserTemplateId(string $type): ?string
    {
        $options = new ListFilesOptions();
        $tokenInfo = $this->client->verifyToken();
        $options->setTags([self::getSandboxTags()[$type], $tokenInfo['description']]);
        $options->setLimit(1);
        $files = $this->client->listFiles($options);
        if ($files) {
            return (string)$files[0]['id'];
        }
        return null;
    }

    private function getExtension(string $type): string
    {
        switch ($type) {
            case self::R_SANDBOX_TYPE:
                return 'R';
            case self::PYTHON_SANDBOX_TYPE:
                return 'py';
        }
    }

    private function downloadFile(string $fileId): string
    {
        $options = new GetFileOptions();
        $options->setFederationToken(true);
        $fileInfo = $this->client->getFile($fileId, $options);

        // Initialize S3Client with credentials from Storage API
        $s3Client = new S3Client([
            'version' => '2006-03-01',
            'region' => $fileInfo['region'],
            'retries' => $this->client->getAwsRetries(),
            'credentials' => [
                'key' => $fileInfo['credentials']['AccessKeyId'],
                'secret' => $fileInfo['credentials']['SecretAccessKey'],
                'token' => $fileInfo['credentials']['SessionToken'],
            ],
            'http' => [
                'decode_content' => false,
            ],
        ]);

        $tmpFileName = sys_get_temp_dir() . '/' . uniqid('data-loader');
        $s3Client->getObject(array(
            'Bucket' => $fileInfo['s3Path']['bucket'],
            'Key' => $fileInfo['s3Path']['key'],
            'SaveAs' => $tmpFileName,
        ));
        return $tmpFileName;
    }

    private function getCommonTemplatePath(string $type): string
    {
        switch ($type) {
            case 'r':
                $file = 'script.R';
                break;
            case 'python':
                $file = 'notebook.ipynb';
                break;
            default:
                throw new LogicException('Invalid template type ' . $type);
        }
        return $templatePath = __DIR__ . '/../res/' . $file;
    }

    private function processTemplate(string $templatePath, string $type, string $script)
    {
        switch ($type) {
            case 'r':
                $template = file_get_contents($templatePath);
                if ($script) {
                    $template .= "\n\n" . $script;
                } else {
                    $this->logger->info('Script is empty.');
                }
                break;
            case 'python':
                $template = file_get_contents($templatePath);
                $templateData = json_decode($template, false, 512, JSON_THROW_ON_ERROR);
                if ($script) {
                    $templateData->cells[] = [
                        'cell_type' => 'code',
                        'execution_count' => null,
                        'metadata' => new \stdClass(),
                        'outputs' => [],
                        'source' => explode("\n", $script),
                    ];
                } else {
                    $this->logger->info('Script is empty.');
                }
                $template = json_encode($templateData, JSON_PRETTY_PRINT + JSON_THROW_ON_ERROR);
                break;
            default:
                throw new LogicException('Invalid template type ' . $type);
        }
        return $template;
    }

    private function getDestinationFile(string $dataDir, string $type): string
    {
        switch ($type) {
            case 'r':
                $file = 'script.R';
                break;
            case 'python':
                $file = 'notebook.ipynb';
                break;
            default:
                throw new LogicException('Invalid template type ' . $type);
        }
        return $dataDir . $file;
    }

    public function processScript(string $dataDir, string $type, string $script): void
    {
        $id = $this->getUserTemplateId($type);
        if (!$id) {
            $id = $this->getProjectTemplateId($type);
        }
        if ($id) {
            $templatePath = $this->downloadFile($id);
        } else {
            $templatePath = $this->getCommonTemplatePath($type);
        }
        $template = $this->processTemplate($templatePath, $type, $script);
        file_put_contents($this->getDestinationFile($dataDir, $type), $template);
    }
}
