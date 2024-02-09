<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Response;

class BlobApiPutTest extends BlobApiTestBase
{
    public function testPutFileNoIdentifier(): void
    {
        $this->createMockClient([
            new Response(200),
        ]);

        try {
            $this->blobApi->putFileByIdentifier('my-identifier', 'test.txt', 'data');
            $this->fail('Expected exception not thrown!');
        } catch (BlobApiError $e) {
            $errorDetails = $e->getErrorDetails();
            $this->assertEquals('File could not be uploaded to Blob!', $e->getMessage());
            $this->assertEquals(BlobApiError::ERROR_ID_PUT_FILE_FAILED, $e->getErrorId());
            $this->assertEquals('No identifier returned from Blob!', $errorDetails['message']);
            $this->assertEquals('test.txt', $errorDetails['fileName']);
        }
    }

    public function testPutFileTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"errorId":"blob:create-file-data-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->putFileByIdentifier('my-identifier', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::ERROR_ID_PUT_FILE_TIMEOUT, $e->getErrorId());
        }
    }

    public function testPutFileFileNotSaved(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"errorId":"blob:something"}'),
        ]);

        try {
            $this->blobApi->putFileByIdentifier('my-identifier', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::ERROR_ID_PUT_FILE_FAILED, $e->getErrorId());
        }
    }

    public function testPutFileBucketQuotaReached(): void
    {
        $this->createMockClient([
            new Response(507, [], '{"errorId":"blob:create-file-data-bucket-quota-reached"}'),
        ]);

        try {
            $this->blobApi->putFileByIdentifier('my-identifier', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::ERROR_ID_PUT_FILE_BUCKET_QUOTA_REACHED, $e->getErrorId());
        }
    }

    public function testPutFileSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        try {
            $identifier = $this->blobApi->putFileByIdentifier('my-identifier', 'test.txt', 'data');
        } catch (BlobApiError $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertEquals('1234', $identifier);
    }
}
