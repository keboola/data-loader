<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\StorageApi\Client;
use Keboola\DataLoader\StorageService;
use PHPUnit\Framework\TestCase;

class StorageServiceTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        $this->client = new Client([
            'token' => getenv('KBC_TEST_TOKEN'),
            'url' => getenv('KBC_TEST_URL'),
        ]);
        parent::setUp();
    }
    public function testSuccess(): void
    {
        self::assertEquals('https://syrup.keboola.com', StorageService::getServiceUrl($this->client, 'syrup'));
    }

    public function testInvalid(): void
    {
        self::expectException(\Exception::class);
        StorageService::getServiceUrl($this->client, 'invalid-service');
    }
}
