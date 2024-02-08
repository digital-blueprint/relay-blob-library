<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Response;

class BlobApiDownloadTest extends BlobApiTestBase
{
    public function testDownloadFileAsContentUrlByIdentifierNotFound(): void
    {
        $this->createMockClient([
            new Response(404),
        ]);

        try {
            $this->blobApi->downloadFileAsContentUrlByIdentifier('1234');
        } catch (BlobApiError $e) {
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
        } catch (BlobApiError $e) {
            $jsonData = json_decode($e->getMessage(), true);
            $this->assertEquals('blob-library:download-file-timeout', $jsonData['errorId']);

            $errorDetails = $jsonData['errorDetails'];
            $this->assertEquals('1234', $errorDetails['identifier']);
        }
    }

    public function testDownloadFileSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"contentUrl":"some-data"}'),
        ]);

        try {
            $contentUrl = $this->blobApi->downloadFileAsContentUrlByIdentifier('1234');
        } catch (BlobApiError $e) {
            $this->fail('Unexpected exception thrown!');
        }

        $this->assertEquals('some-data', $contentUrl);
    }
}
