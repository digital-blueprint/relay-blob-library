<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\HttpFileApi;

class BlobHttpApiTest extends BlobHttpApiTestBase
{
    /**
     * @throws BlobApiError
     */
    public function testCreateHttpModeConfig(): void
    {
        $this->blobApi =
            BlobApi::createHttpModeApi('test-bucket', 'key', 'https://blob.com/', true,
                'https://auth.com/', 'client', 'secret');

        $this->assertInstanceOf(HttpFileApi::class, $this->blobApi->getBlobFileApiImpl());
    }

    /**
     * @throws BlobApiError
     */
    public function testCreateSignedUrl(): void
    {
        $url = $this->blobApi->createSignedUrl('POST', ['prefix' => 'my-prefix']);
        $this->validateUrl($url, 'POST', extraQueryParameters: ['prefix' => 'my-prefix']);

        $url = $this->blobApi->createSignedUrl('GET', [], [BlobApi::INCLUDE_DELETE_AT_OPTION => true], '1234');
        $this->validateUrl($url, 'GET', identifier: '1234', extraQueryParameters: [BlobApi::INCLUDE_DELETE_AT_OPTION => '1']);

        $url = $this->blobApi->createSignedUrl('GET', [], [BlobApi::INCLUDE_FILE_CONTENTS_OPTION => true], '1234');
        $this->validateUrl($url, 'GET', identifier: '1234', extraQueryParameters: [BlobApi::INCLUDE_FILE_CONTENTS_OPTION => '1']);

        $url = $this->blobApi->createSignedUrl('GET', [], [
            BlobApi::PREFIX_OPTION => 'my-prefix',
            BlobApi::PREFIX_STARTS_WITH_OPTION => true,
            BlobApi::INCLUDE_DELETE_AT_OPTION => true,
            BlobApi::INCLUDE_FILE_CONTENTS_OPTION => true,
        ]);
        $this->validateUrl($url, 'GET', extraQueryParameters: [
            BlobApi::PREFIX_OPTION => 'my-prefix',
            BlobApi::PREFIX_STARTS_WITH_OPTION => '1',
            BlobApi::INCLUDE_DELETE_AT_OPTION => '1',
            BlobApi::INCLUDE_FILE_CONTENTS_OPTION => '1',
        ]);

        $url = $this->blobApi->createSignedUrl('DELETE', [],
            [BlobApi::INCLUDE_DELETE_AT_OPTION => true], '1234');
        $this->validateUrl($url, 'DELETE', identifier: '1234',
            extraQueryParameters: [BlobApi::INCLUDE_DELETE_AT_OPTION => '1']);

        $url = $this->blobApi->createSignedUrl('PATCH', ['prefix' => 'my-prefix'], [], '1234');
        $this->validateUrl($url, 'PATCH', extraQueryParameters: ['prefix' => 'my-prefix'], identifier: '1234');

        $url = $this->blobApi->createSignedUrl('GET', [],
            [BlobApi::INCLUDE_DELETE_AT_OPTION => true], '1234', 'download');
        $this->validateUrl($url, 'GET', identifier: '1234',
            action: 'download', extraQueryParameters: [BlobApi::INCLUDE_DELETE_AT_OPTION => '1']);
    }
}
