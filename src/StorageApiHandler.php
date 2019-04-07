<?php

declare(strict_types=1);

namespace Keboola\DataLoader;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;
use function Keboola\Utils\sanitizeUtf8;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;

class StorageApiHandler extends AbstractHandler
{
    /**
     * @var Client
     */
    protected $storageApiClient;

    /**
     * @var string
     */
    private $appName;

    public function __construct(string $appName, Client $storageApiClient)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApiClient;
        parent::__construct();
    }

    public function handle(array $record): bool
    {
        if ($record['level'] === Logger::DEBUG) {
            return false;
        }

        $event = new Event();
        $event->setComponent($this->appName);
        $event->setMessage(sanitizeUtf8($record['message']));
        $event->setRunId($this->storageApiClient->getRunId());

        switch ($record['level']) {
            case Logger::ERROR:
                $type = Event::TYPE_ERROR;
                break;
            case Logger::CRITICAL:
            case Logger::EMERGENCY:
            case Logger::ALERT:
                $type = Event::TYPE_ERROR;
                $event->setMessage('Application error');
                $event->setDescription('Please contact the support for help.');
                break;
            case Logger::WARNING:
            case Logger::NOTICE:
                $type = Event::TYPE_WARN;
                break;
            case Logger::INFO:
            default:
                $type = Event::TYPE_INFO;
                break;
        }
        $event->setType($type);

        $this->storageApiClient->createEvent($event);
        return false;
    }
}
