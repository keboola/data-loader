<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Configuration\File;
use Keboola\InputMapping\Configuration\Table;
use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\StorageApi\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ScriptProcessor
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function getExtension(string $type): string
    {
        switch ($type) {
            case 'r':
                return 'R';
            case 'python':
                return 'py';
        }
    }

    public function processScript(string $dataDir, string $type, string $script): void
    {
        if ($script) {
            file_put_contents($dataDir . 'main.' . $this->getExtension($type), $script);
        } else {
            $this->logger->info('Script is empty.', ['runId' => $runId]);
        }
    }
}
