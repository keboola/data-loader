<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Reader\WorkspaceProviderInterface;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Workspaces;

class WorkspaceProvider implements WorkspaceProviderInterface
{
    /** @var string $workspaceId */
    private $workspaceId;

    /** @var Client $storageApiClient */
    private $storageApiClient;

    /** @var string $workspacePassword */
    private $workspacePassword;

    public function __construct(Client $storageApiClient, string $workspaceId, string $workspacePassword)
    {
        $this->workspaceId = $workspaceId;
        $this->workspacePassword = $workspacePassword;
        $this->storageApiClient = $storageApiClient;
    }

    private function getWorkspace(): array
    {
        $workspaces = new Workspaces($this->storageApiClient);
        return $workspaces->getWorkspace($this->workspaceId);
    }

    /**
     * @inheritDoc
     */
    public function getWorkspaceId($type): string
    {
        return $this->workspaceId;
    }

    public function cleanup(): void
    {
        // Not implemented, not required here
        return;
    }

    /**
     * @inheritDoc
     */
    public function getCredentials($type): array
    {
        $workspace = $this->getWorkspace();
        return [
            'host' => $workspace['connection']['host'],
            'warehouse' => $workspace['connection']['warehouse'],
            'database' => $workspace['connection']['database'],
            'schema' => $workspace['connection']['schema'],
            'user' => $workspace['connection']['user'],
            'password' => $this->workspacePassword,
        ];
    }
}
