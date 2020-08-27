<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\InputMapping\Exception\InvalidInputException;
use Keboola\InputMapping\Reader\Reader;
use Keboola\InputMapping\Reader\WorkspaceProviderInterface;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Workspaces;

class WorkspaceProvider implements WorkspaceProviderInterface
{
    const WORKSPACE_TYPE_STAGING_MAP = [
        self::TYPE_SNOWFLAKE => Reader::STAGING_SNOWFLAKE,
        self::TYPE_REDSHIFT => Reader::STAGING_REDSHIFT,
        self::TYPE_SYNAPSE => Reader::STAGING_SYNAPSE,
    ];

    /** @var string $workspaceId */
    private $workspaceId;

    /** @var string $workspacePassword */
    private $workspacePassword;

    /** @var string $workspaceType */
    private $workspaceType;

    /** @var Client $storageApiClient */
    private $storageApiClient;

    public function __construct(
        Client $storageApiClient,
        string $workspaceId,
        string $workspacePassword,
        string $workspaceType
    ) {
        $this->storageApiClient = $storageApiClient;
        $this->workspaceId = $workspaceId;
        $this->workspacePassword = $workspacePassword;
        $this->workspaceType = $workspaceType;
    }

    private function getWorkspace(): array
    {
        $workspaces = new Workspaces($this->storageApiClient);
        return $workspaces->getWorkspace($this->workspaceId);
    }

    public function getWorkspaceStagingName(): string
    {
        if (!array_key_exists($this->workspaceType, self::WORKSPACE_TYPE_STAGING_MAP)) {
            throw new InvalidInputException('Invalid workspace type provided: ' . $this->workspaceType);
        }
        return self::WORKSPACE_TYPE_STAGING_MAP[$this->workspaceType];
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
