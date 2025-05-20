<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class BlobHttpApiGetTest extends BlobHttpApiTestBase
{
    /**
     * @throws BlobApiError
     */
    public function testGetFileSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = $this->blobApi->getFile('1234');
        $this->assertEquals('1234', $blobFile->getIdentifier());

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234');
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFileSuccessAuthenticated(): void
    {
        $this->createWithAuthentication();

        $requestHistory = [];
        $this->createMockClient([
            new Response(201, [], '{"token_endpoint": "https://example.com/get_token"}'),
            new Response(201, [], '{"access_token": "foobar", "expires_in": 3600}'),
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $blobFile = $this->blobApi->getFile('1234');
        $this->assertEquals('1234', $blobFile->getIdentifier());

        $request = $requestHistory[2]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234');
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFilesSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, body: '{"hydra:member": [{"identifier":"1234"},{"identifier":"1235"}]}'),
        ], $requestHistory);

        $blobFiles = $this->blobApi->getFiles();
        $this->assertCount(2, $blobFiles);
        $this->assertEquals('1234', $blobFiles[0]->getIdentifier());
        $this->assertEquals('1235', $blobFiles[1]->getIdentifier());

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', extraQueryParams: [
            'page' => '1',
            'perPage' => '30',
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFilesSuccessAuthenticated(): void
    {
        $this->createWithAuthentication();

        $requestHistory = [];
        $this->createMockClient([
            new Response(201, [], '{"token_endpoint": "https://example.com/get_token"}'),
            new Response(201, [], '{"access_token": "foobar", "expires_in": 3600}'),
            new Response(200, body: '{"hydra:member": [{"identifier":"1234"},{"identifier":"1235"}]}'),
        ], $requestHistory);

        $blobFiles = $this->blobApi->getFiles();
        $this->assertCount(2, $blobFiles);
        $this->assertEquals('1234', $blobFiles[0]->getIdentifier());
        $this->assertEquals('1235', $blobFiles[1]->getIdentifier());

        $request = $requestHistory[2]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', extraQueryParams: [
            'page' => '1',
            'perPage' => '30',
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFileIncludeDataSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234", "contentUrl": "some_url"}'),
        ], $requestHistory);

        $options = [];
        BlobApi::setIncludeFileContents($options, true);
        $blobFile = $this->blobApi->getFile('1234', $options);
        $this->assertEquals('1234', $blobFile->getIdentifier());
        $this->assertEquals('some_url', $blobFile->getContentUrl());

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234', extraQueryParams: [
            'includeData' => '1',
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFileIncludeDeleteAtSuccess(): void
    {
        $requestHistory = [];
        $this->createMockClient([
            new Response(200, [], '{"identifier":"1234"}'),
        ], $requestHistory);

        $options = [];
        BlobApi::setIncludeDeleteAt($options, true);
        $blobFile = $this->blobApi->getFile('1234', $options);
        $this->assertEquals('1234', $blobFile->getIdentifier());

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234', extraQueryParams: [
            'includeDeleteAt' => '1',
        ]);
    }

    public function testGetFileForbidden(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"relay:errorId":"blob:check-signature-creation-time-bad-format"}'),
        ]);

        try {
            $this->blobApi->getFile('1234');
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Getting file failed', $blobApiError->getMessage());
            $this->assertEquals(403, $blobApiError->getStatusCode());
            $this->assertEquals('blob:check-signature-creation-time-bad-format', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testGetFilesForbidden(): void
    {
        $this->createMockClient([
            new Response(403, [], '{"relay:errorId":"blob:check-signature-creation-time-bad-format"}'),
        ]);

        try {
            $this->blobApi->getFiles();
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Getting files failed', $blobApiError->getMessage());
            $this->assertEquals(403, $blobApiError->getStatusCode());
            $this->assertEquals('blob:check-signature-creation-time-bad-format', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testGettingFileServerError(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"relay:errorId":"blob:something", "relay:errorDetails": {"foo":"bar"}}'),
        ]);

        try {
            $this->blobApi->getFile('1234');
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Getting file failed', $blobApiError->getMessage());
            $this->assertEquals(500, $blobApiError->getStatusCode());
            $this->assertEquals('blob:something', $blobApiError->getBlobErrorId());
            $this->assertEquals(['foo' => 'bar'], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testGettingFilesServerError(): void
    {
        $this->createMockClient([
            new Response(500, [], '{"relay:errorId":"blob:something", "relay:errorDetails": {"foo":"bar"}}'),
        ]);

        try {
            $this->blobApi->getFiles();
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Getting files failed', $blobApiError->getMessage());
            $this->assertEquals(500, $blobApiError->getStatusCode());
            $this->assertEquals('blob:something', $blobApiError->getBlobErrorId());
            $this->assertEquals(['foo' => 'bar'], $blobApiError->getBlobErrorDetails());
        }
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFileResponseSuccess(): void
    {
        $requestHistory = [];
        $content = 'this is a data stream';
        $this->createMockClient([
            new Response(200, headers: [
                'Content-Type' => 'text/plain',
                'Content-Length' => (string) strlen($content),
            ], body: $content),
        ], $requestHistory);

        ob_start();
        $this->blobApi->getFileResponse('1234')->sendContent();
        $actualContent = ob_get_clean();
        $this->assertEquals($content, $actualContent);

        $request = $requestHistory[0]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234', 'download');
    }

    /**
     * @throws BlobApiError
     */
    public function testGetFileResponseSuccessAuthenticated(): void
    {
        $this->createWithAuthentication();

        $requestHistory = [];
        $content = 'this is a data stream';
        $this->createMockClient([
            new Response(201, [], '{"token_endpoint": "https://example.com/get_token"}'),
            new Response(201, [], '{"access_token": "foobar", "expires_in": 3600}'),
            new Response(200, headers: [
                'Content-Type' => 'text/plain',
                'Content-Length' => (string) strlen($content),
            ], body: $content),
        ], $requestHistory);

        ob_start();
        $this->blobApi->getFileResponse('1234')->sendContent();
        $actualContent = ob_get_clean();
        $this->assertEquals($content, $actualContent);

        $request = $requestHistory[2]['request'];
        assert($request instanceof Request);
        $this->validateRequest($request, 'GET', '1234', 'download');
    }

    public function testGetFileResponseForbidden(): void
    {
        $this->createMockClient([
            new Response(403, body: '{"relay:errorId":"blob:check-signature-creation-time-bad-format"}'),
        ]);

        try {
            $this->blobApi->getFileResponse('1234')->sendContent();
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::CLIENT_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Downloading file failed', $blobApiError->getMessage());
            $this->assertEquals(403, $blobApiError->getStatusCode());
            $this->assertEquals('blob:check-signature-creation-time-bad-format', $blobApiError->getBlobErrorId());
            $this->assertEquals([], $blobApiError->getBlobErrorDetails());
        }
    }

    public function testGetFileResponseServerError(): void
    {
        $this->createMockClient([
            new Response(500, body: '{"relay:errorId":"blob:something", "relay:errorDetails": {"foo":"bar"}}'),
        ]);

        try {
            $this->blobApi->getFileResponse('1234')->sendContent();
            $this->fail('Expected BlobApiError');
        } catch (BlobApiError $blobApiError) {
            $this->assertEquals(BlobApiError::SERVER_ERROR, $blobApiError->getErrorId());
            $this->assertEquals('Downloading file failed', $blobApiError->getMessage());
            $this->assertEquals(500, $blobApiError->getStatusCode());
            $this->assertEquals('blob:something', $blobApiError->getBlobErrorId());
        }
    }
}
