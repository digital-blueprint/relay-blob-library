<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Response;

class BlobApiUploadTest extends BlobApiTestBase
{
    public function testUploadFileNoIdentifier(): void
    {
        $this->createMockClient([
            new Response(200),
        ]);

        try {
            $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
            $this->fail('Expected exception not thrown!');
        } catch (BlobApiError $e) {
            $errorDetails = $e->getErrorDetails();
            $this->assertEquals('File could not be uploaded to Blob!', $e->getMessage());
            $this->assertEquals('blob-library:upload-file-failed', $e->getErrorId());
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
        } catch (BlobApiError $e) {
            $this->assertEquals('blob-library:upload-file-timeout', $e->getErrorId());
        }
    }

    public function testUploadFileFileNotSaved(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"errorId":"blob:file-not-saved"}'),
        ]);

        try {
            $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->assertEquals('blob-library:upload-file-not-saved', $e->getErrorId());
        }
    }

    public function testUploadFileBucketQuotaReached(): void
    {
        $this->createMockClient([
            new Response(507, [], '{"errorId":"blob:create-file-data-bucket-quota-reached"}'),
        ]);

        try {
            $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->assertEquals('blob-library:upload-file-bucket-quota-reached', $e->getErrorId());
        }
    }

    public function testUploadFileSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        try {
            $identifier = $this->blobApi->uploadFile('prefix', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertEquals('1234', $identifier);
    }
}
