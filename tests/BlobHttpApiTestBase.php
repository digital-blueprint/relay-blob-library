<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\HttpFileApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

class BlobHttpApiTestBase extends TestCase
{
    protected ?BlobApi $blobApi = null;

    /**
     * @throws BlobApiError
     */
    protected function setUp(): void
    {
        parent::setUp();

        $blobBaseUrl = 'https://api.your.server';
        $blobBucketId = 'your-bucket-id';
        $blobKey = 'NN2fRdPQAjJvLe9w3SjzkW85ZMisGMyCTQsMhZrn68xJ9NwsXt';

        $config = [
            'blob_library' => [
                'use_http_mode' => true,
                'bucket_identifier' => $blobBucketId,
                'http_mode' => [
                    'bucket_key' => $blobKey,
                    'blob_base_url' => $blobBaseUrl,
                    'oidc_enabled' => false,
                ],
            ],
        ];

        // Create a new BlobApi instance
        $this->blobApi = BlobApi::createFromConfig($config);
    }

    protected function createMockClient(array $queue): void
    {
        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $blobFileApiImpl = $this->blobApi->getBlobFileApiImpl();
        assert($blobFileApiImpl instanceof HttpFileApi);
        $blobFileApiImpl->setClient($client);
    }
}
