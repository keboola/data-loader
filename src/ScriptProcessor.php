<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Aws\S3\S3Client;
use Keboola\DataLoader\ScriptProcessor\PythonTemplateAdapter;
use Keboola\DataLoader\ScriptProcessor\RTemplateAdapter;
use Keboola\DataLoader\ScriptProcessor\TemplateAdapter;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Options\GetFileOptions;
use Keboola\StorageApi\Options\ListFilesOptions;
use LogicException;
use Psr\Log\LoggerInterface;

class ScriptProcessor
{
    public const PYTHON_SANDBOX_TEMPLATE_TAG = '_python_sandbox_template_';
    public const R_SANDBOX_TEMPLATE_TAG = '_r_sandbox_template_';
    public const PYTHON_SANDBOX_TYPE = 'python';
    public const R_SANDBOX_TYPE = 'r';

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
            self::PYTHON_SANDBOX_TYPE => self::PYTHON_SANDBOX_TEMPLATE_TAG,
            self::R_SANDBOX_TYPE => self::R_SANDBOX_TEMPLATE_TAG,
        ];
    }

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    private function getProjectTemplateId(string $type): ?string
    {
        $options = new ListFilesOptions();
        $options->setTags([self::getSandboxTags()[$type]]);
        $options->setLimit(1);
        $files = $this->client->listFiles($options);
        if ($files) {
            $file = $files[0];
            $this->logger->info(
                sprintf(
                    'Found project template: "%s", created "%s", ID: %s.',
                    $file['name'],
                    $file['created'],
                    $file['id']
                )
            );
            return (string) $file['id'];
        }
        return null;
    }

    private function getUserTemplateId(string $type): ?string
    {
        $options = new ListFilesOptions();
        $tokenInfo = $this->client->verifyToken();
        // can't use setTags because they're connected via OR condition
        $options->setQuery(sprintf(
            '(tags: "%s") AND (tags: "%s")',
            self::getSandboxTags()[$type],
            $tokenInfo['description']
        ));
        $options->setLimit(1);
        $files = $this->client->listFiles($options);
        if ($files) {
            $file = $files[0];
            $this->logger->info(
                sprintf(
                    'Found user template: "%s", created "%s", ID: %s.',
                    $file['name'],
                    $file['created'],
                    $file['id']
                )
            );
            return (string) $file['id'];
        }
        return null;
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

    private function getTemplateAdapter(string $type): TemplateAdapter
    {
        switch ($type) {
            case 'python':
                return new PythonTemplateAdapter();
                break;
            case 'r':
                return new RTemplateAdapter();
                break;
            default:
                throw new LogicException('Invalid template type ' . $type);
        }
    }

    public function processScript(string $dataDir, string $type, string $script): void
    {
        $adapter = $this->getTemplateAdapter($type);
        $id = $this->getUserTemplateId($type);
        if (!$id) {
            $id = $this->getProjectTemplateId($type);
        }
        if ($id) {
            $templatePath = $this->downloadFile($id);
        } else {
            $this->logger->info('Found no user-defined template, using built-in.');
            $templatePath = $adapter->getCommonTemplatePath();
        }
        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new InvalidInputException('Failed to read template from path ' . $templatePath);
        }
        if ($script) {
            $template = $adapter->processTemplate($template, $script);
        } else {
            $this->logger->info('The script is empty.');
        }
        if (file_put_contents($adapter->getDestinationFile($dataDir), $template) === false) {
            throw new InvalidInputException(
                'Failed to save template to path ' . $adapter->getDestinationFile($dataDir)
            );
        }
    }
}
