<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\HttpFileApi;
use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
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
        $uri = new Uri($url);

        $path = $uri->getPath();
        $this->assertEquals(
            '/blob/files'.($identifier ? '/'.$identifier : '').($action ? '/'.$action : ''), $path);
        $this->assertEquals(self::BLOB_BASE_URL, $uri->getScheme().'://'.$uri->getHost());

        $query = $uri->getQuery();
        $queryParts = explode('&', $query);
        $queryParams = [];
        foreach ($queryParts as $queryParts) {
            $parts = explode('=', $queryParts);
            $queryParams[$parts[0]] = urldecode($parts[1]);
        }

        // consider that the second the url was created might have passed
        $dateTime = new \DateTimeImmutable();
        $dateTimeString = $dateTime->format(\DateTimeInterface::ATOM);
        $dateTimeMinusOneSecondString = $dateTime->modify('-1 second')->format(\DateTimeInterface::ATOM);
        $this->assertTrue(in_array($queryParams['creationTime'], [$dateTimeString, $dateTimeMinusOneSecondString], true));

        $extraQueryParameters = array_merge($extraQueryParameters, [
            'bucketIdentifier' => self::BUCKET_IDENTIFIER,
            'method' => $method,
            'creationTime' => $queryParams['creationTime'],  // and use the original creation time for comparison
        ]);

        $query = $uri->getQuery();
        $pos = strrpos($query, '&');
        $queryWithoutSig = substr($query, 0, $pos);

        $url = $path.'?'.$queryWithoutSig;
        $checksum = SignatureTools::generateSha256Checksum($url);
        $payload = [
            'ucs' => $checksum,
        ];
        $expectedSig = SignatureTools::createSignature(self::BUCKET_KEY, $payload);

        foreach ($queryParams as $paramName => $paramValue) {
            if ($paramName === 'sig') {
                $this->assertEquals($expectedSig, $paramValue);
            } else {
                $this->assertEquals($extraQueryParameters[$paramName], $paramValue);
            }
        }
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
