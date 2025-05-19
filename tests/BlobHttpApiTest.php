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
}
