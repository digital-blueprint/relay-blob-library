<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use PHPUnit\Framework\TestCase;

class SignedPayloadTest extends TestCase
{
    public function testEmptyPayload(): void
    {
        try {
            $secret = bin2hex(random_bytes(32));
            $bucketId = '9876';
            $creationTime = date('U');
            //            $uri = 'http://127.0.0.1:8000/blob/files/';
            $payload = [
                'bucketID' => $bucketId,
                'creationTime' => $creationTime,
                'prefix' => 'MyApp/MyShare',
            ];

            $token = SignatureTools::createSignature($secret, $payload);

            $data = SignatureTools::verifySignature($secret, $token);
            $this->assertIsArray($data);
            $this->assertEquals($payload['bucketID'], $data['bucketID']);
            $this->assertEquals($payload['creationTime'], $data['creationTime']);
            $this->assertEquals($payload['prefix'], $data['prefix']);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testPayload(): void
    {
        try {
            $secret = bin2hex(random_bytes(32));
            $bucketId = '9876';
            $creationTime = date('U');
            //            $uri = 'http://127.0.0.1:8000/blob/files/';
            $payload = [
                'bucketID' => $bucketId,
                'creationTime' => $creationTime,
                'prefix' => 'MyApp/MyShare',
                'filename' => 'test.txt',
                'file' => hash('sha256', file_get_contents(__DIR__.'/test.txt')),
                'metadata' => [],
            ];

            $token = SignatureTools::createSignature($secret, $payload);

            $data = SignatureTools::verifySignature($secret, $token);
            $this->assertEquals($payload['bucketID'], $data['bucketID']);
            $this->assertEquals($payload['creationTime'], $data['creationTime']);
            $this->assertEquals($payload['prefix'], $data['prefix']);
            $this->assertEquals($payload['filename'], $data['filename']);
            $this->assertEquals($payload['file'], $data['file']);
            $this->assertEquals($payload['metadata'], $data['metadata']);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testEmptyUrl(): void
    {
        try {
            $secret = bin2hex(random_bytes(32));
            $bucketId = '9876';
            $creationTime = date('U');
            //            $uri = "http://127.0.0.1:8000/blob/files/?bucketID=$bucketId&prefix=$prefix&creationTime=$creationTime";
            $payload = [
                'bucketID' => $bucketId,
                'creationTime' => $creationTime,
                'prefix' => 'MyApp/MyShare',
            ];

            $token = SignatureTools::createSignature($secret, $payload);

            $data = SignatureTools::verifySignature($secret, $token);
            $this->assertIsArray($data);
            $this->assertEquals($payload['bucketID'], $data['bucketID']);
            $this->assertEquals($payload['creationTime'], $data['creationTime']);
            $this->assertEquals($payload['prefix'], $data['prefix']);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testInvalidSignature(): void
    {
        $secret = bin2hex(random_bytes(32));
        $secret2 = bin2hex(random_bytes(32));
        $bucketId = '9876';
        $creationTime = date('U');
        $payload = [
            'bucketID' => $bucketId,
            'creationTime' => $creationTime,
            'prefix' => 'MyApp/MyShare',
        ];

        try {
            $token = SignatureTools::createSignature($secret, $payload);
            SignatureTools::verifySignature($secret2, $token);
        } catch (BlobApiError $e) {
            $this->assertEquals('blob-library:invalid-signature', $e->getErrorId());

            return;
        } catch (\JsonException $e) {
            $this->fail($e->getMessage());
        }

        $this->fail('Should have thrown an exception');
    }
}
