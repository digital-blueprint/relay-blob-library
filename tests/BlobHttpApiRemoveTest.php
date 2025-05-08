<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Response;

class BlobHttpApiRemoveTest extends BlobHttpApiTestBase
{
    public function testRemoveFileNotFound(): void
    {
        $this->createMockClient([
            new Response(204),
        ]);

        $this->blobApi->removeFile('1234');
        $this->assertTrue(true);
    }

    public function testRemoveFileForbiddenSignature(): void
    {
        $this->createMockClient([
            new Response(403, body: '{"relay:errorId":"blob:check-signature-creation-time-too-old"}'),
        ]);

        try {
            $this->blobApi->removeFile('1234');
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $e->getErrorId());
            $this->assertEquals('Removing file failed', $e->getMessage());
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('blob:check-signature-creation-time-too-old', $e->getBlobErrorId());
            $this->assertEquals([], $e->getBlobErrorDetails());
        }
    }

    public function testRemoveFileForbidden(): void
    {
        $this->createMockClient([
            new Response(403),
        ]);

        try {
            $this->blobApi->removeFile('1234');
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $e->getErrorId());
            $this->assertEquals('Removing file failed', $e->getMessage());
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals(null, $e->getBlobErrorId());
            $this->assertEquals([], $e->getBlobErrorDetails());
        }
    }

    public function testRemoveFileFailedFileNotFound(): void
    {
        $this->createMockClient([
            new Response(404, body: '{"relay:errorId":"blob:file-data-not-found"}'),
        ]);

        try {
            $this->blobApi->removeFile('1234');
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::FILE_NOT_FOUND, $e->getErrorId());
            $this->assertEquals('Removing file failed', $e->getMessage());
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertEquals('blob:file-data-not-found', $e->getBlobErrorId());
            $this->assertEquals([], $e->getBlobErrorDetails());
        }
    }

    /**
     * 404 errors are ignored.
     */
    public function testRemoveFilesNotFound(): void
    {
        $this->createMockClient([
            new Response(200, body: '{"hydra:member": [{"identifier":"1234"}]}'),
            new Response(404),
        ]);

        $this->blobApi->removeFiles(['prefix' => 'my-prefix']);
        $this->assertTrue(true);
    }

    public function testRemoveFilesFailed(): void
    {
        $this->createMockClient([
            new Response(403, []),
        ]);

        try {
            $this->blobApi->removeFiles(['prefix' => 'my-prefix']);
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $e->getErrorId());
            $this->assertEquals('Getting files failed', $e->getMessage());
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals(null, $e->getBlobErrorId());
            $this->assertEquals([], $e->getBlobErrorDetails());
        }
    }
}
