<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

class BlobHttpApiAddTest extends BlobHttpApiTestBase
{
    /**
     * @throws BlobApiError
     */
    public function testAddFileStringSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile('data');

        $blobFile = $this->blobApi->addFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'POST', extraQueryParams: [
            'prefix' => 'prefix',
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public function testAddFileStringSuccessAuthenticated(): void
    {
        $this->createWithAuthentication();

        $requestHistory = [];
        $this->createMockClient([
            new Response(201, [], '{"token_endpoint": "https://example.com/get_token"}'),
            new Response(201, [], '{"access_token": "foobar", "expires_in": 3600}'),
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile('data');

        $blobFile = $this->blobApi->addFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
        $request = $requestHistory[2]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'POST', extraQueryParams: [
            'prefix' => 'prefix',
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public function testAddFileResourceSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile(fopen(__DIR__.'/test.txt', 'r'));

        $blobFile = $this->blobApi->addFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
    }

    public function testAddFileSplFileInfoSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile(new \SplFileInfo(__DIR__.'/test.txt'));

        $blobFile = $this->blobApi->addFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
    }

    public function testAddFileSplFileInfoNotReadable(): void
    {
        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile(new \SplFileInfo(__DIR__.'/foo.txt'));

        try {
            $this->blobApi->addFile($blobFile);
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::FILE_NOT_READABLE, $blobApiError->getErrorId());
        }
    }

    public function testAddFileStreamInterfaceSuccess(): void
    {
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile(Utils::streamFor(fopen(__DIR__.'/test.txt', 'r')));

        $blobFile = $this->blobApi->addFile($blobFile);
        $this->assertEquals('1234', $blobFile->getIdentifier());
    }

    public function testAddFileForbidden(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"relay:errorId":"blob:create-file-data-creation-time-too-old"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile('data');

        try {
            $this->blobApi->addFile($blobFile);
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $e) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $e->getErrorId());
            $this->assertEquals('Adding file failed', $e->getMessage());
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('blob:create-file-data-creation-time-too-old', $e->getBlobErrorId());
            $this->assertEquals([], $e->getBlobErrorDetails());
        }
    }

    public function testAddFileFileNotSaved(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"relay:errorId":"blob:file-not-saved", "relay:errorDetails": {"foo":"bar"}}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile('data');

        try {
            $this->blobApi->addFile($blobFile);
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Adding file failed', $blobApiError->getMessage());
            $this->assertEquals(500, $blobApiError->getStatusCode());
            $this->assertEquals('blob:file-not-saved', $blobApiError->getBlobErrorId());
            $this->assertEquals(['foo' => 'bar'], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testAddFileBucketQuotaReached(): void
    {
        $this->createMockClient([
            new Response(507, [], '{"relay:errorId":"blob:create-file-data-bucket-quota-reached"}'),
        ]);

        $blobFile = new BlobFile();
        $blobFile->setPrefix('prefix');
        $blobFile->setFileName('test.txt');
        $blobFile->setFile('data');

        try {
            $this->blobApi->addFile($blobFile);
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Adding file failed', $blobApiError->getMessage());
            $this->assertEquals(507, $blobApiError->getStatusCode());
            $this->assertEquals('blob:create-file-data-bucket-quota-reached', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }
}
