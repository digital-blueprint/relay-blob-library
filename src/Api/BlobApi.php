<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Dbp\Relay\BlobLibrary\Helpers\Error;
use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

class BlobApi
{
    /**
     * @var mixed
     */
    private $blobKey;
    /**
     * @var mixed
     */
    private $blobBucketId;

    /**
     * @var string
     */
    private $blobBaseUrl;

    /**
     * @var Client
     */
    private $client;

    public function __construct(string $blobBaseUrl, string $blobBucketId, $blobKey)
    {
        $this->blobBaseUrl = $blobBaseUrl;
        $this->blobKey = $blobKey;
        $this->blobBucketId = $blobBucketId;

        $this->client = new Client();
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @throws Error
     */
    public function createBlobSignature($payload): string
    {
        try {
            return SignatureTools::create($this->blobKey, $payload);
        } catch (\JsonException $e) {
            throw Error::withDetails('Payload could not be signed for blob storage!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws Error
     */
    public function deleteFileByIdentifier(string $identifier): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'action' => 'DELETEONE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('DELETE', $url);
        } catch (GuzzleException $e) {
            throw Error::withDetails('File could not be deleted from Blob!', 'blob-library:delete-file-failed', ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        if ($statusCode !== 204) {
            throw Error::withDetails('File could not be deleted from Blob!', 'blob-library:delete-file-failed', ['identifier' => $identifier, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    /**
     * @throws Error
     */
    public function deleteFilesByPrefix(string $prefix): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $prefix,
            'action' => 'DELETEALL',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        // We send a DELETE request to the blob service to delete all files with the given prefix,
        // regardless if we have files in dispatch or not, we just want to make sure that the blob files are deleted
        try {
            $r = $this->client->request('DELETE', $url);
        } catch (GuzzleException $e) {
            // 404 errors are ok, because the files might not exist anymore
            if ($e->getCode() === 404) {
                return;
            }

            throw Error::withDetails('Files could not be deleted from Blob!', 'blob-library:delete-files-failed', ['prefix' => $prefix, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        // 404 errors are ok, because the files might not exist anymore
        if ($statusCode !== 204 && $statusCode !== 404) {
            throw Error::withDetails('Files could not be deleted from Blob!', 'blob-library:delete-files-failed', ['prefix' => $prefix, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    /**
     * @throws Error
     */
    public function downloadFileAsContentUrlByIdentifier(string $identifier): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'action' => 'GETONE',
            'binary' => 1,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions, GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw Error::withDetails('File was not found!', 'blob-library:download-file-not-found', ['identifier' => $identifier]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = Error::decodeErrorId($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw Error::withDetails('Request too old and timed out! Please try again.', 'blob-library:download-file-timeout', ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                }
            }

            throw Error::withDetails('File could not be downloaded from Blob!', 'blob-library:download-file-failed', ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw Error::withDetails('Result could not be decoded!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }

        $contentUrl = $jsonData['contentUrl'] ?? '';

        if ($contentUrl === '') {
            throw Error::withDetails('File could not be downloaded from Blob!', 'blob-library:download-content-url-empty', ['identifier' => $identifier, 'message' => 'No contentUrl returned from Blob!']);
        }

        return $contentUrl;
    }

    /**
     * @throws Error
     */
    public function uploadFile(string $prefix, string $fileName, string $fileData): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $prefix,
            'action' => 'CREATEONE',
            'fileName' => $fileName,
            'fileHash' => SignatureTools::generateSha256Checksum($fileData),
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // Post to Blob
        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('POST', $url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $fileData,
                        'filename' => $fileName,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            // Handle ClientExceptions (403) and ServerException (500)
            // GuzzleExceptions will be caught by the general Exception handler
            if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $errorId = Error::decodeErrorId($body);

                switch ($statusCode) {
                    case 403:
                        if ($errorId === 'blob:create-file-data-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw Error::withDetails('Request too old and timed out! Please try again.', 'blob-library:upload-file-timeout', ['message' => $e->getMessage()]);
                        }
                        break;
                    case 500:
                        if ($errorId === 'blob:file-not-saved') {
                            throw Error::withDetails('File could not be saved!', 'blob-library:upload-file-not-saved', ['message' => $e->getMessage()]);
                        }
                        break;
                    case 507:
                        if ($errorId === 'blob:create-file-data-bucket-quota-reached') {
                            throw Error::withDetails('Bucket quota is reached!', 'blob-library:upload-file-bucket-quota-reached', ['message' => $e->getMessage()]);
                        }
                        break;
                }
            }

            throw Error::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['prefix' => $prefix, 'fileName' => $fileName, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw Error::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['prefix' => $prefix, 'fileName' => $fileName, 'message' => 'No identifier returned from Blob!']);
        }

        // Return the blob file ID
        return $identifier;
    }

    /**
     * @throws Error
     */
    protected function getSignedBlobFilesUrl(array $queryParams, string $blobIdentifier = ''): string
    {
        $path = '/blob/files';

        if ($blobIdentifier !== '') {
            $path .= '/'.urlencode($blobIdentifier);
        }

        // It's mandatory that "%20" is used instead of "+" for spaces in the query string, otherwise the checksum will be invalid!
        $urlPart = $path.'?'.http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $checksum = SignatureTools::generateSha256Checksum($urlPart);

        $payload = [
            'cs' => $checksum,
        ];

        $token = $this->createBlobSignature($payload);

        return $this->blobBaseUrl.$urlPart.'&sig='.$token;
    }
}
