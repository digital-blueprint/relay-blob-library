<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
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
     * @throws BlobApiError
     */
    public function createBlobSignature($payload): string
    {
        try {
            return SignatureTools::create($this->blobKey, $payload);
        } catch (\JsonException $e) {
            throw BlobApiError::withDetails('Payload could not be signed for blob storage!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws BlobApiError
     */
    public function deleteFileByIdentifier(string $identifier): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'method' => 'DELETE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('DELETE', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // 404 errors are ok, because the file might not exist anymore
                        return;
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = BlobApiError::decodeErrorId($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:delete-file-timeout', ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw BlobApiError::withDetails('File could not be deleted from Blob!', 'blob-library:delete-file-failed', ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        if ($statusCode !== 204) {
            throw BlobApiError::withDetails('File could not be deleted from Blob!', 'blob-library:delete-file-failed', ['identifier' => $identifier, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    /**
     * @throws BlobApiError
     */
    public function deleteFilesByPrefix(string $prefix): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $prefix,
            'method' => 'DELETE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        // We send a DELETE request to the blob service to delete all files with the given prefix,
        // regardless if we have files in dispatch or not, we just want to make sure that the blob files are deleted
        try {
            $r = $this->client->request('DELETE', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // 404 errors are ok, because the files might not exist anymore
                        return;
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = BlobApiError::decodeErrorId($body);

                        if ($errorId === 'blob:delete-file-data-by-prefix-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:delete-files-timeout', ['prefix' => $prefix, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw BlobApiError::withDetails('Files could not be deleted from Blob!', 'blob-library:delete-files-failed', ['prefix' => $prefix, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        // 404 errors are ok, because the files might not exist anymore
        if ($statusCode !== 204 && $statusCode !== 404) {
            throw BlobApiError::withDetails('Files could not be deleted from Blob!', 'blob-library:delete-files-failed', ['prefix' => $prefix, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    /**
     * @throws BlobApiError
     */
    public function getFileDataByIdentifier(string $identifier, int $includeData = 1): array
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'method' => 'GET',
            'includeData' => $includeData,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw BlobApiError::withDetails('File was not found!', 'blob-library:download-file-not-found', ['identifier' => $identifier]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = BlobApiError::decodeErrorId($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:download-file-timeout', ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw BlobApiError::withDetails('File could not be downloaded from Blob!', 'blob-library:download-file-failed', ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw BlobApiError::withDetails('Result could not be decoded!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }

        return $jsonData;
    }

    public function getFileDataByPrefix(string $prefix, int $includeData = 1): array
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $prefix,
            'method' => 'GET',
            'includeData' => $includeData,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw BlobApiError::withDetails('Files were not found!', 'blob-library:download-file-not-found', ['prefix' => $prefix]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = BlobApiError::decodeErrorId($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:download-file-timeout', ['prefix' => $prefix, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw BlobApiError::withDetails('File could not be downloaded from Blob!', 'blob-library:download-file-failed', ['prefix' => $prefix, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw BlobApiError::withDetails('Result could not be decoded!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }

        return $jsonData;
    }

    /**
     * @throws BlobApiError
     */
    public function downloadFileAsContentUrlByIdentifier(string $identifier): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'method' => 'GET',
            'includeData' => 1,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->client->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw BlobApiError::withDetails('File was not found!', 'blob-library:download-file-not-found', ['identifier' => $identifier]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = BlobApiError::decodeErrorId($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:download-file-timeout', ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw BlobApiError::withDetails('File could not be downloaded from Blob!', 'blob-library:download-file-failed', ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw BlobApiError::withDetails('Result could not be decoded!', 'blob-library:json-exception', ['message' => $e->getMessage()]);
        }

        $contentUrl = $jsonData['contentUrl'] ?? '';

        if ($contentUrl === '') {
            throw BlobApiError::withDetails('File could not be downloaded from Blob!', 'blob-library:download-content-url-empty', ['identifier' => $identifier, 'message' => 'No contentUrl returned from Blob!']);
        }

        return $contentUrl;
    }

    /**
     * @throws BlobApiError
     */
    public function uploadFile(string $prefix, string $fileName, string $fileData, string $additionalMetadata = '', string $additionalType = ''): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $prefix,
            'method' => 'POST',
            'fileName' => $fileName,
            'fileHash' => SignatureTools::generateSha256Checksum($fileData),
        ];

        $url = $this->getSignedBlobFilesUrlWithBody($queryParams, $additionalMetadata, $additionalType);

        // Post to Blob
        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $options = [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $fileData,
                        'filename' => $fileName,
                    ],
                ],
            ];
            if ($additionalType) {
                array_push($options['multipart'],
                    [
                    'name' => 'additionalMetadata',
                    'contents' => $additionalMetadata,
                    ],
                    [
                    'name' => 'additionalType',
                    'contents' => $additionalType,
                    ]
                );
            } elseif ($additionalMetadata) {
                $options['multipart'][] = [
                    'name' => 'additionalMetadata',
                    'contents' => $additionalMetadata,
                ];
            }
            $r = $this->client->request('POST', $url, $options);
        } catch (\Exception $e) {
            // Handle ClientExceptions (403) and ServerException (500)
            // GuzzleExceptions will be caught by the general Exception handler
            if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $errorId = BlobApiError::decodeErrorId($body);

                switch ($statusCode) {
                    case 403:
                        if ($errorId === 'blob:create-file-data-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:upload-file-timeout', ['message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                        break;
                    case 500:
                        if ($errorId === 'blob:file-not-saved') {
                            throw BlobApiError::withDetails('File could not be saved!', 'blob-library:upload-file-not-saved', ['message' => $e->getMessage()]);
                        }
                        break;
                    case 507:
                        if ($errorId === 'blob:create-file-data-bucket-quota-reached') {
                            throw BlobApiError::withDetails('Bucket quota is reached!', 'blob-library:upload-file-bucket-quota-reached', ['message' => $e->getMessage()]);
                        }
                        break;
                }
            }

            throw BlobApiError::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['prefix' => $prefix, 'fileName' => $fileName, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw BlobApiError::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['prefix' => $prefix, 'fileName' => $fileName, 'message' => 'No identifier returned from Blob!']);
        }

        // Return the blob file ID
        return $identifier;
    }

    /**
     * @throws BlobApiError
     */
    public function putFileByIdentifier(string $identifier, string $fileName = '', string $additionalMetadata = '', string $additionalType = ''): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'method' => 'PUT',
        ];

        $url = $this->getSignedBlobFilesUrlWithBody($queryParams, $additionalMetadata, $additionalType, $fileName, $identifier);

        // set fileName, addMetaData and addType of body to json encode later
        $body = [];
        if ($fileName) {
            $body['fileName'] = $fileName;
        }
        if ($additionalMetadata) {
            $body['additionalMetadata'] = $additionalMetadata;
        }
        if ($additionalType) {
            $body['additionalType'] = $additionalType;
        }

        // PUT to Blob
        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $options = [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'HTTP_ACCEPT' => 'application/ld+json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($body),
            ];
            $r = $this->client->request('PUT', $url, $options);
        } catch (\Exception $e) {
            // Handle ClientExceptions (403) and ServerException (500)
            // GuzzleExceptions will be caught by the general Exception handler
            if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $errorId = BlobApiError::decodeErrorId($body);

                switch ($statusCode) {
                    case 403:
                        if ($errorId === 'blob:create-file-data-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw BlobApiError::withDetails('Request too old and timed out! Please try again.', 'blob-library:upload-file-timeout', ['message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                        break;

                    case 405:
                        if ($errorId === 'blob:create-file-data-method-not-suitable') {
                            throw BlobApiError::withDetails('The given method in url is not the same as the used method! Please try again.', 'blob-library:put-file-method-not-suitable', ['message' => $e->getMessage()]);
                        }
                        break;

                    case 507:
                        if ($errorId === 'blob:create-file-data-bucket-quota-reached') {
                            throw BlobApiError::withDetails('The bucket quota of the given bucket is reached! Please try again or contact your bucket owner.', 'blob-library:put-file-bucket-quota-reached', ['message' => $e->getMessage()]);
                        }
                        break;
                }
            }

            throw BlobApiError::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['identifier' => $identifier, 'fileName' => $fileName, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw BlobApiError::withDetails('File could not be uploaded to Blob!', 'blob-library:upload-file-failed', ['identifier' => $identifier, 'fileName' => $fileName, 'message' => 'No identifier returned from Blob!']);
        }

        // Return the blob file ID
        return $identifier;
    }

    /**
     * @throws BlobApiError
     */
    protected function handleSignatureError(string $errorId, BadResponseException $e)
    {
        if ($errorId === 'blob:checksum-invalid') {
            throw BlobApiError::withDetails('The signature check was successful, but one of the given checksums ucs or bcs is invalid. Please try again.', 'blob-library:checksum-invalid', ['message' => $e->getMessage()]);
        }
        if ($errorId === 'blob:signature-invalid') {
            throw BlobApiError::withDetails('The signature check was not successful. Maybe your key is invalid, or something went wrong while signing. Please try again', 'blob-library:signature-invalid', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws BlobApiError
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
            'ucs' => $checksum,
        ];

        $token = $this->createBlobSignature($payload);

        return $this->blobBaseUrl.$urlPart.'&sig='.$token;
    }

    /**
     * @throws BlobApiError
     */
    protected function getSignedBlobFilesUrlWithBody(array $queryParams, string $additionalMetadata = '', string $additionalType = '', string $filename = '', string $identifier = ''): string
    {
        $path = '/blob/files';

        if ($identifier) {
            $path = $path.'/'.$identifier;
        }

        $body = [];

        // It's mandatory that "%20" is used instead of "+" for spaces in the query string, otherwise the checksum will be invalid!
        $urlPart = $path.'?'.http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        if ($filename) {
            $body['fileName'] = $filename;
        }
        if ($additionalMetadata) {
            $body['additionalMetadata'] = $additionalMetadata;
        }
        if ($additionalType) {
            $body['additionalType'] = $additionalType;
        }

        $body = json_encode($body, JSON_FORCE_OBJECT);

        $checksum = SignatureTools::generateSha256Checksum($urlPart);
        $bcs = SignatureTools::generateSha256Checksum($body);

        $payload = [
            'ucs' => $checksum,
            'bcs' => $bcs,
        ];

        $token = $this->createBlobSignature($payload);

        return $this->blobBaseUrl.$urlPart.'&sig='.$token;
    }
}
