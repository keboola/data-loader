<?php

declare(strict_types=1);

namespace Keboola\DataLoader\FunctionalTests;

use Keboola\DataLoader\StorageApiHandler;
use Keboola\StorageApi\Client;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class StorageApiHandlerTest extends TestCase
{
    public function setUp(): void
    {
        if (empty(getenv('KBC_TEST_TOKEN')) || empty(getenv('KBC_TEST_URL'))) {
            self::fail('KBC_TEST_TOKEN and KBC_TEST_URL environment variables must be set.');
        }
        parent::setUp();
    }

    public function testHandler(): void
    {
        $client = new Client(['token' => getenv('KBC_TEST_TOKEN'), 'url' => getenv('KBC_TEST_URL')]);
        $testRunId = uniqid('data-loader-test');
        $client->setRunId($testRunId);
        $handler = new StorageApiHandler('app-name', $client);
        $message = ['level' => Logger::ERROR, 'message' => 'test error message'];
        $handler->handle($message);
        $message = ['level' => Logger::CRITICAL, 'message' => 'test critical message'];
        $handler->handle($message);
        $message = ['level' => Logger::EMERGENCY, 'message' => 'test emergency message'];
        $handler->handle($message);
        $message = ['level' => Logger::ALERT, 'message' => 'test alert message'];
        $handler->handle($message);
        $message = ['level' => Logger::WARNING, 'message' => 'test warning message'];
        $handler->handle($message);
        $message = ['level' => Logger::NOTICE, 'message' => 'test notice message'];
        $handler->handle($message);
        $message = ['level' => Logger::INFO, 'message' => 'test info message ' . chr(240) . chr(159) . '|'];
        $handler->handle($message);
        $message = ['level' => Logger::DEBUG, 'message' => 'test debug message'];
        $handler->handle($message);
        $events = [];
        sleep(2);
        foreach ($client->listEvents() as $event) {
            if ($event['runId'] === $testRunId) {
                $events[$event['id']] = [
                    'event' => $event['event'],
                    'component' => $event['component'],
                    'message' => $event['message'],
                    'description' => $event['description'],
                    'type' => $event['type'],
                ];
            }
        }
        ksort($events);
        self::assertEquals(
            [
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'test error message',
                    'description' => '',
                    'type' => 'error',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'Application error',
                    'description' => 'Please contact Keboola Support for help.',
                    'type' => 'error',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'Application error',
                    'description' => 'Please contact Keboola Support for help.',
                    'type' => 'error',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'Application error',
                    'description' => 'Please contact Keboola Support for help.',
                    'type' => 'error',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'test warning message',
                    'description' => '',
                    'type' => 'warn',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'test notice message',
                    'description' => '',
                    'type' => 'warn',
                ],
                [
                    'event' => 'ext.app-name.',
                    'component' => 'app-name',
                    'message' => 'test info message |',
                    'description' => '',
                    'type' => 'info',
                ],
            ],
            array_values($events)
        );
    }
}
