<?php

namespace Keboola\DataLoader;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;
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

    /**
     * StorageApiHandler constructor.
     * @param string $appName
     * @param Client $storageApiClient
     */
    public function __construct($appName, Client $storageApiClient)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApiClient;
        parent::__construct();
    }

    public function handle(array $record)
    {
        if ($record['level'] == Logger::DEBUG) {
            return false;
        }

        $event = new Event();
        $event->setComponent($this->appName);
        $event->setMessage(\Keboola\Utils\sanitizeUtf8($record['message']));
        $event->setRunId($this->storageApiClient->getRunId());
        $results = [];
        if (isset($record['context']['exceptionId'])) {
            $results['exceptionId'] = $record['context']['exceptionId'];
        }
        if (empty(getenv('KBC_RUNID'))) {
            $results['job'] = getenv('KBC_RUNID');
        }
        $event->setResults($results);

        switch ($record['level']) {
            case Logger::ERROR:
                $type = Event::TYPE_ERROR;
                break;
            case Logger::CRITICAL:
            case Logger::EMERGENCY:
            case Logger::ALERT:
                $type = Event::TYPE_ERROR;
                $event->setMessage("Application error");
                $event->setDescription("Contact support@keboola.com");
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
