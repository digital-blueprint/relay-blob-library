<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

class BlobApiTestBase extends TestCase
{
    protected $blobApi;

    protected function setUp(): void
    {
        parent::setUp();

        $blobBaseUrl = 'https://api.your.server';
        $blobBucketId = 'your-bucket-id';
        $blobKey = 'NN2fRdPQAjJvLe9w3SjzkW85ZMisGMyCTQsMhZrn68xJ9NwsXt';

        // Create a new BlobApi instance
        $this->blobApi = new BlobApi($blobBaseUrl, $blobBucketId, $blobKey);
    }

    protected function createMockClient(array $queue)
    {
        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->blobApi->setClient($client);
    }
}
