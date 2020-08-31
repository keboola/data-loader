<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\StorageApi\Client;

class StorageService
{
    public static function getServiceUrl(Client $client, string $name): string
    {
        $index = $client->indexAction();
        $service = array_filter($index['services'], function (array $service) use ($name) {
            return $service['id'] === $name;
        });
        if (!count($service)) {
            throw new \Exception("Service $name not found in Storage index");
        }
        return current($service)['url'];
    }
}
