<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\HttpFileApi;
use Dbp\Relay\BlobLibrary\Helpers\TestUtils;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class BlobHttpApiTestBase extends TestCase
{
    protected const BLOB_BASE_URL = 'https://blob.com';
    protected const BUCKET_IDENTIFIER = 'test-bucket';
    protected const BUCKET_KEY = 'b3fc39dc89a4106a9c529555067722729f3b52c88dfd071bc9fed61345e62eb3';
    protected ?BlobApi $blobApi = null;

    protected const TEST_CONFIG = [
        'blob_library' => [
            'use_http_mode' => true,
            'bucket_identifier' => self::BUCKET_IDENTIFIER,
            'http_mode' => [
                'bucket_key' => self::BUCKET_KEY,
                'blob_base_url' => self::BLOB_BASE_URL,
                'oidc_enabled' => false,
            ],
        ],
    ];

    /**
     * @throws BlobApiError
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createWithoutAuthentication();
    }

    /**
     * @throws BlobApiError
     */
    protected function createWithoutAuthentication(): void
    {
        $this->blobApi = BlobApi::createFromConfig(self::TEST_CONFIG);
    }

    protected function createWithAuthentication(): void
    {
        $config = self::TEST_CONFIG;
        $config['blob_library']['http_mode']['oidc_enabled'] = true;
        $config['blob_library']['http_mode']['oidc_provider_url'] = 'https://auth.com';
        $config['blob_library']['http_mode']['oidc_client_id'] = 'client';
        $config['blob_library']['http_mode']['oidc_client_secret'] = 'secret';

        $this->blobApi = BlobApi::createFromConfig($config);
    }

    protected function validateRequest(Request $request, string $method, ?string $identifier = null,
        ?string $appendix = null, array $extraQueryParams = []): void
    {
        $this->assertEquals($method, $request->getMethod());
        if (in_array($method, ['POST', 'PATCH'], true)) {
            $this->assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        }

        $this->validateUrl($request->getUri()->__toString(), $method, $identifier, $appendix, $extraQueryParams);
    }

    protected function validateUrl(string $url, string $method, ?string $identifier = null,
        ?string $action = null, array $extraQueryParameters = []): void
    {
        TestUtils::validateSignedUrl($this, self::BUCKET_IDENTIFIER, self::BUCKET_KEY, self::BLOB_BASE_URL,
            $url, $method, $identifier, $action, $extraQueryParameters);
    }

    protected function createMockClient(array $responses, &$requestHistory = null): void
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        if ($requestHistory !== null) {
            $handlerStack->push(Middleware::history($requestHistory));
        }
        $client = new Client(['handler' => $handlerStack]);

        $blobFileApiImpl = $this->blobApi->getBlobFileApiImpl();
        assert($blobFileApiImpl instanceof HttpFileApi);

        $blobFileApiImpl->setClient($client);
    }
}
