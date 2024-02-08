<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Response;

class BlobApiDeleteTest extends BlobApiTestBase
{
    /**
     * 404 errors are ignored.
     */
    public function testDeleteFileNotFound(): void
    {
        $this->createMockClient([
            new Response(404),
        ]);

        try {
            $this->blobApi->deleteFileByIdentifier('1234');
        } catch (BlobApiError $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertTrue(true);
    }

    public function testDeleteFileTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"errorId":"blob:check-signature-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->deleteFileByIdentifier('1234');
        } catch (BlobApiError $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:delete-file-timeout', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('1234', $errorDetails['identifier']);
        }
    }

    public function testDeleteFileFailed(): void
    {
        $this->createMockClient([
            new Response(403, []),
        ]);

        try {
            $this->blobApi->deleteFileByIdentifier('1234');
        } catch (BlobApiError $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:delete-file-failed', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('1234', $errorDetails['identifier']);
        }
    }

    /**
     * 404 errors are ignored.
     */
    public function testDeleteFilesNotFound(): void
    {
        $this->createMockClient([
            new Response(404),
        ]);

        try {
            $this->blobApi->deleteFilesByPrefix('my-prefix');
        } catch (BlobApiError $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertTrue(true);
    }

    public function testDeleteFilesTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"errorId":"blob:delete-file-data-by-prefix-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->deleteFilesByPrefix('my-prefix');
        } catch (BlobApiError $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:delete-files-timeout', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('my-prefix', $errorDetails['prefix']);
        }
    }

    public function testDeleteFilesFailed(): void
    {
        $this->createMockClient([
            new Response(403, []),
        ]);

        try {
            $this->blobApi->deleteFilesByPrefix('my-prefix');
        } catch (BlobApiError $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:delete-files-failed', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('my-prefix', $errorDetails['prefix']);
        }
    }
}
