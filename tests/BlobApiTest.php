<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Helpers\Error;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BlobApiTest extends TestCase
{
    private $blobApi;

    protected function setUp(): void
    {
        parent::setUp();

        $blobBaseUrl = 'https://api.your.server';
        $blobBucketId = 'your-bucket-id';
        $blobKey = 'NN2fRdPQAjJvLe9w3SjzkW85ZMisGMyCTQsMhZrn68xJ9NwsXt';

        // Create a new BlobApi instance
        $this->blobApi = new BlobApi($blobBaseUrl, $blobBucketId, $blobKey);
    }

    private function createMockClient(array $queue)
    {
        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->blobApi->setClient($client);
    }

    public function testUploadFileNoIdentifier(): void
    {
        $this->createMockClient([
            new Response(200),
        ]);

        try {
            $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
            $this->fail('Expected exception not thrown!');
        } catch (Error $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('File could not be uploaded to Blob!', $jsonData['message']);
            $this->assertEquals('blob-library:upload-file-failed', $jsonData['errorId']);
            $this->assertEquals('No identifier returned from Blob!', $errorDetails['message']);
            $this->assertEquals('prefix', $errorDetails['prefix']);
            $this->assertEquals('test.txt', $errorDetails['fileName']);
        }
    }

    public function testUploadFileTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"errorId":"blob:create-file-data-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
        } catch (Error $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:upload-file-timeout', $jsonData['errorId']);
        }
    }

    public function testUploadFileSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        try {
            $identifier = $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
        } catch (Error $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertEquals('1234', $identifier);
    }

    public function testDownloadFileAsContentUrlByIdentifierNotFound(): void
    {
        $this->createMockClient([
            new Response(404),
        ]);

        try {
            $this->blobApi->downloadFileAsContentUrlByIdentifier('1234');
        } catch (Error $e) {
            $jsonData = json_decode($e->getMessage(), true);
//            $this->assertEquals('File could not be downloaded from Blob!', $jsonData['message']);
            $this->assertEquals('blob-library:download-file-not-found', $jsonData['errorId']);
            $this->assertEquals('File was not found!', $jsonData['message']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('1234', $errorDetails['identifier']);
        }
    }

    public function testDownloadFileTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"errorId":"blob:check-signature-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->downloadFileAsContentUrlByIdentifier('1234');
        } catch (Error $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:download-file-timeout', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('1234', $errorDetails['identifier']);
        }
    }
}
