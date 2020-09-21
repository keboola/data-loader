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
    public const WORKSPACE_TYPE_STAGING_MAP = [
        self::TYPE_SNOWFLAKE => Reader::STAGING_SNOWFLAKE,
        self::TYPE_REDSHIFT => Reader::STAGING_REDSHIFT,
        self::TYPE_SYNAPSE => Reader::STAGING_SYNAPSE,
    ];

    /** @var string $workspaceId */
    private $workspaceId;

    /** @var string $workspacePassword */
    private $workspacePassword;

    /** @var Client $storageApiClient */
    private $storageApiClient;

    public function __construct(
        string $workspaceId,
        string $workspacePassword,
        Client $storageApiClient
    ) {
        $this->workspaceId = $workspaceId;
        $this->workspacePassword = $workspacePassword;
        $this->storageApiClient = $storageApiClient;
    }

    private function getWorkspace(): array
    {
        $workspaces = new Workspaces($this->storageApiClient);
        return $workspaces->getWorkspace($this->workspaceId);
    }

    public function getWorkspaceStagingName(): string
    {
        $workspace =  $this->getWorkspace();
        if (!array_key_exists($workspace['connection']['backend'], self::WORKSPACE_TYPE_STAGING_MAP)) {
            throw new InvalidInputException(
                'Staging storage for ' . $workspace['connection']['backend'] . ' workspace is not supported'
            );
        }
        return self::WORKSPACE_TYPE_STAGING_MAP[$workspace['connection']['backend']];
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
