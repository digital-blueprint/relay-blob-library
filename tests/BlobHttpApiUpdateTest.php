<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

class BlobHttpApiUpdateTest extends BlobHttpApiTestBase
{
    /**
     * @throws BlobApiError
     */
    public function testPatchFileStringSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile('data 2');

        $blobFile = $this->blobApi->updateFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'PATCH', '1234');
    }

    /**
     * @throws BlobApiError
     */
    public function testPatchFileStringSuccessAuthenticated(): void
    {
        $this->createWithAuthentication();

        $requestHistory = [];
        $this->createMockClient([
            new Response(201, [], '{"token_endpoint": "https://example.com/get_token"}'),
            new Response(201, [], '{"access_token": "foobar", "expires_in": 3600}'),
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile('data 2');

        $blobFile = $this->blobApi->updateFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());

        $request = $requestHistory[2]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'PATCH', '1234');
    }

    /**
     * @throws BlobApiError
     */
    public function testPatchFileSplInfoSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile(new \SplFileInfo(__DIR__.'/test.txt'));

        $blobFile = $this->blobApi->updateFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
    }

    /**
     * @throws BlobApiError
     */
    public function testPatchFileStreamSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile(Utils::streamFor(fopen(__DIR__.'/test.txt', 'r')));

        $blobFile = $this->blobApi->updateFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
    }

    public function testPatchFileTimeout(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"relay:errorId":"blob:create-file-data-creation-time-too-old"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile('data 2');

        try {
            $this->blobApi->updateFile($blobFile);
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Updating file failed', $blobApiError->getMessage());
            $this->assertEquals(403, $blobApiError->getStatusCode());
            $this->assertEquals('blob:create-file-data-creation-time-too-old', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testPatchFileFileNotSaved(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"relay:errorId":"blob:something", "relay:errorDetails": {"foo":"bar"}}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile('data 2');

        try {
            $this->blobApi->updateFile($blobFile);
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Updating file failed', $blobApiError->getMessage());
            $this->assertEquals(500, $blobApiError->getStatusCode());
            $this->assertEquals('blob:something', $blobApiError->getBlobErrorId());
            $this->assertEquals(['foo' => 'bar'], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testPatchFileBucketQuotaReached(): void
    {
        $this->createMockClient([
            new Response(507, [], '{"relay:errorId":"blob:create-file-data-bucket-quota-reached"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setFileName('test.txt');
        $blobFile->setIdentifier('1234');
        $blobFile->setFile('data 2');

        try {
            $this->blobApi->updateFile($blobFile);
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Updating file failed', $blobApiError->getMessage());
            $this->assertEquals(507, $blobApiError->getStatusCode());
            $this->assertEquals('blob:create-file-data-bucket-quota-reached', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }
}
