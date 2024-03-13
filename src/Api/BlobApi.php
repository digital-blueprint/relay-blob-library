<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

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

    /**
     * @var string
     */
    private $token;

    public function __construct(string $blobBaseUrl, string $blobBucketId, $blobKey)
    {
        $this->blobBaseUrl = $blobBaseUrl;
        $this->blobKey = $blobKey;

        // $blobBucketId should be not encoded previously!
        $this->blobBucketId = rawurlencode($blobBucketId);

        $this->client = new Client();

        // empty keycloak token by default
        $this->token = '';
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function getAndSetOAuth2Token($keycloakUrl, $realm, $clientID, $clientSecret)
    {
        // Fetch a token
        $tokenUrl = "$keycloakUrl/realms/$realm/protocol/openid-connect/token";
        $client = new Client();
        $response = $client->post(
            $tokenUrl, [
            'auth' => [$clientID, $clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $data = (string) $response->getBody();
        $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        $this->token = $json['access_token'];

        return $json['access_token'];
    }

    /**
     * @throws BlobApiError
     */
    public function createBlobSignature($payload): string
    {
        try {
            return SignatureTools::create($this->blobKey, $payload);
        } catch (\JsonException $e) {
            throw new BlobApiError('Payload could not be signed for blob storage!', BlobApiError::ERROR_ID_JSON_EXCEPTION, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws BlobApiError
     */
    public function deleteFileByIdentifier(string $identifier): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'method' => 'DELETE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->request('DELETE', $url);
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
                        $errorId = self::getErrorIdFromApiError($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_DELETE_FILE_TIMEOUT, ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw new BlobApiError('File could not be deleted from Blob!', BlobApiError::ERROR_ID_DELETE_FILE_FAILED, ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        if ($statusCode !== 204) {
            throw new BlobApiError('File could not be deleted from Blob!', BlobApiError::ERROR_ID_DELETE_FILE_FAILED, ['identifier' => $identifier, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    /**
     * @throws BlobApiError
     */
    public function deleteFilesByPrefix(string $prefix): void
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'prefix' => $prefix,
            'method' => 'DELETE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        // We send a DELETE request to the blob service to delete all files with the given prefix,
        // regardless if we have files in dispatch or not, we just want to make sure that the blob files are deleted
        try {
            $r = $this->request('DELETE', $url);
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
                        $errorId = self::getErrorIdFromApiError($body);

                        if ($errorId === 'blob:delete-file-data-by-prefix-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError(
                                'Request too old and timed out! Please try again.',
                                BlobApiError::ERROR_ID_DELETE_FILES_TIMEOUT,
                                ['prefix' => $prefix, 'message' => $e->getMessage()]
                            );
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw new BlobApiError(
                'Files could not be deleted from Blob!',
                BlobApiError::ERROR_ID_DELETE_FILES_FAILED,
                ['prefix' => $prefix, 'message' => $e->getMessage()]
            );
        }

        $statusCode = $r->getStatusCode();

        // 404 errors are ok, because the files might not exist anymore
        if ($statusCode !== 204 && $statusCode !== 404) {
            throw new BlobApiError(
                'Files could not be deleted from Blob!',
                BlobApiError::ERROR_ID_DELETE_FILE_FAILED,
                ['prefix' => $prefix, 'message' => 'Blob returned status code '.$statusCode]
            );
        }
    }

    /**
     * @throws BlobApiError
     */
    public function getFileDataByIdentifier(string $identifier, int $includeData = 1): array
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'method' => 'GET',
            'includeData' => $includeData,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw new BlobApiError('File was not found!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_NOT_FOUND, ['identifier' => $identifier]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = self::getErrorIdFromApiError($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_DOWNLOAD_FILE_TIMEOUT, ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw new BlobApiError('File could not be downloaded from Blob!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_FAILED, ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BlobApiError('Result could not be decoded!', BlobApiError::ERROR_ID_JSON_EXCEPTION, ['message' => $e->getMessage()]);
        }

        return $jsonData;
    }

    public function getFileDataByPrefix(string $prefix, int $includeData = 1): array
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'prefix' => $prefix,
            'method' => 'GET',
            'includeData' => $includeData,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw new BlobApiError('Files were not found!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_NOT_FOUND, ['prefix' => $prefix]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = self::getErrorIdFromApiError($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_DOWNLOAD_FILE_TIMEOUT, ['prefix' => $prefix, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw new BlobApiError('File could not be downloaded from Blob!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_FAILED, ['prefix' => $prefix, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BlobApiError('Result could not be decoded!', BlobApiError::ERROR_ID_JSON_EXCEPTION, ['message' => $e->getMessage()]);
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
            'creationTime' => rawurlencode(date('c')),
            'method' => 'GET',
            'includeData' => 1,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $identifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $r = $this->request('GET', $url);
        } catch (\Exception $e) {
            // Handle ClientExceptions. GuzzleExceptions will be caught by the general Exception handler
            if ($e instanceof ClientException && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();

                switch ($statusCode) {
                    case 404:
                        // Handle 404 errors distinctively
                        throw new BlobApiError('File was not found!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_NOT_FOUND, ['identifier' => $identifier]);
                    case 403:
                        $body = $response->getBody()->getContents();
                        $errorId = self::getErrorIdFromApiError($body);

                        if ($errorId === 'blob:check-signature-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_DOWNLOAD_FILE_TIMEOUT, ['identifier' => $identifier, 'message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                }
            }

            throw new BlobApiError('File could not be downloaded from Blob!', BlobApiError::ERROR_ID_DOWNLOAD_FILE_FAILED, ['identifier' => $identifier, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();

        try {
            $jsonData = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BlobApiError('Result could not be decoded!', BlobApiError::ERROR_ID_JSON_EXCEPTION, ['message' => $e->getMessage()]);
        }

        $contentUrl = $jsonData['contentUrl'] ?? '';

        if ($contentUrl === '') {
            throw new BlobApiError('File could not be downloaded from Blob!', BlobApiError::ERROR_ID_DOWNLOAD_CONTENT_URL_EMPTY, ['identifier' => $identifier, 'message' => 'No contentUrl returned from Blob!']);
        }

        return $contentUrl;
    }

    /**
     * Uploads a file to the Blob service.
     *
     * @param string $prefix             the prefix of the file
     * @param string $fileName           the name of the file
     * @param string $fileData           the data of the file
     * @param string $additionalMetadata additional metadata for the file (optional)
     * @param string $additionalType     additional type for the file (optional)
     *
     * @return string the identifier of the uploaded file
     *
     * @throws BlobApiError if the file upload fails
     */
    public function uploadFile(string $prefix, string $fileName, string $fileData, string $additionalMetadata = '', string $additionalType = ''): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'prefix' => $prefix,
            'method' => 'POST',
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
                    [
                        'name' => 'fileName',
                        'contents' => $fileName,
                    ],
                    [
                        'name' => 'fileHash',
                        'contents' => SignatureTools::generateSha256Checksum($fileData),
                    ],
                ],
            ];
            if ($additionalType) {
                $options['multipart'][] = [
                    'name' => 'additionalType',
                    'contents' => $additionalType,
                ];
            }
            if ($additionalMetadata) {
                $options['multipart'][] = [
                    'name' => 'additionalMetadata',
                    'contents' => $additionalMetadata,
                ];
            }
            $r = $this->request('POST', $url, $options);
        } catch (\Exception $e) {
            // Handle ClientExceptions (403) and ServerException (500)
            // GuzzleExceptions will be caught by the general Exception handler
            if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $errorId = self::getErrorIdFromApiError($body);

                switch ($statusCode) {
                    case 403:
                        if ($errorId === 'blob:create-file-data-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_UPLOAD_FILE_TIMEOUT, ['message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                        break;
                    case 500:
                        if ($errorId === 'blob:file-not-saved') {
                            throw new BlobApiError('File could not be saved!', BlobApiError::ERROR_ID_UPLOAD_FILE_NOT_SAVED, ['message' => $e->getMessage()]);
                        }
                        break;
                    case 507:
                        if ($errorId === 'blob:create-file-data-bucket-quota-reached') {
                            throw new BlobApiError('Bucket quota is reached!', BlobApiError::ERROR_ID_UPLOAD_FILE_BUCKET_QUOTA_REACHED, ['message' => $e->getMessage()]);
                        }
                        break;
                }
            }

            throw new BlobApiError('File could not be uploaded to Blob!', BlobApiError::ERROR_ID_UPLOAD_FILE_FAILED, ['prefix' => $prefix, 'fileName' => $fileName, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw new BlobApiError('File could not be uploaded to Blob!', BlobApiError::ERROR_ID_UPLOAD_FILE_FAILED, ['prefix' => $prefix, 'fileName' => $fileName, 'message' => 'No identifier returned from Blob!']);
        }

        // Return the blob file ID
        return $identifier;
    }

    /**
     * Updates a file identified by its identifier in the Blob service.
     *
     * @param string $identifier         the identifier of the file
     * @param string $fileName           the new name of the file (optional)
     * @param string $additionalMetadata additional metadata for the file (optional)
     * @param string $additionalType     additional type for the file (optional)
     *
     * @return string the updated identifier of the file
     *
     * @throws BlobApiError if the file update fails
     */
    public function putFileByIdentifier(string $identifier, string $fileName = '', string $additionalMetadata = '', string $additionalType = '', string $fileData = ''): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => rawurlencode(date('c')),
            'method' => 'PATCH',
        ];

        $url = $this->getSignedBlobFilesUrlWithBody($queryParams, $additionalMetadata, $additionalType, $fileName, $identifier);

        // set fileName, addMetaData and addType of body
        $options = [];

        if ($fileData) {
            $options['multipart'][] = [
                [
                    'name' => 'file',
                    'contents' => $fileData,
                    'filename' => $fileName,
                ],
                [
                    'name' => 'fileHash',
                    'contents' => SignatureTools::generateSha256Checksum($fileData),
                ],
            ];
        }

        if ($fileName) {
            $options['multipart'][] = [
                'name' => 'fileName',
                'contents' => $fileName,
            ];
        }
        if ($additionalType) {
            $options['multipart'][] = [
                'name' => 'additionalType',
                'contents' => $additionalType,
            ];
        }
        if ($additionalMetadata) {
            $options['multipart'][] = [
                'name' => 'additionalMetadata',
                'contents' => $additionalMetadata,
            ];
        }

        // PATCH to Blob
        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        try {
            $options['headers'][] = [
                    'Accept' => 'application/ld+json',
                    'HTTP_ACCEPT' => 'application/ld+json',
                    'Content-Type' => 'application/json',
            ];
            $r = $this->request('PATCH', $url, $options);
        } catch (\Exception $e) {
            // Handle ClientExceptions (403) and ServerException (500)
            // GuzzleExceptions will be caught by the general Exception handler
            if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $errorId = self::getErrorIdFromApiError($body);

                switch ($statusCode) {
                    case 403:
                        if ($errorId === 'blob:create-file-data-creation-time-too-old') {
                            // The parameter creationTime is too old, therefore the request timed out and a new request has to be created, signed and sent
                            throw new BlobApiError('Request too old and timed out! Please try again.', BlobApiError::ERROR_ID_PUT_FILE_TIMEOUT, ['message' => $e->getMessage()]);
                        }
                        $this->handleSignatureError($errorId, $e);
                        break;

                    case 405:
                        if ($errorId === 'blob:create-file-data-method-not-suitable') {
                            throw new BlobApiError('The given method in url is not the same as the used method! Please try again.', BlobApiError::ERROR_ID_PUT_FILE_METHOD_NOT_SUITABLE, ['message' => $e->getMessage()]);
                        }
                        break;

                    case 507:
                        if ($errorId === 'blob:create-file-data-bucket-quota-reached') {
                            throw new BlobApiError('The bucket quota of the given bucket is reached! Please try again or contact your bucket owner.', BlobApiError::ERROR_ID_PUT_FILE_BUCKET_QUOTA_REACHED, ['message' => $e->getMessage()]);
                        }
                        break;
                }
            }

            throw new BlobApiError('File could not be uploaded to Blob!', BlobApiError::ERROR_ID_PUT_FILE_FAILED, ['identifier' => $identifier, 'fileName' => $fileName, 'message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw new BlobApiError('File could not be uploaded to Blob!', BlobApiError::ERROR_ID_PUT_FILE_FAILED, ['identifier' => $identifier, 'fileName' => $fileName, 'message' => 'No identifier returned from Blob!']);
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
            throw new BlobApiError('The signature check was successful, but one of the given checksums ucs or bcs is invalid. Please try again.', BlobApiError::ERROR_ID_CHECKSUM_INVALID, ['message' => $e->getMessage()]);
        }
        if ($errorId === 'blob:signature-invalid') {
            throw new BlobApiError('The signature check was not successful. Maybe your key is invalid, or something went wrong while signing. Please try again', BlobApiError::ERROR_ID_SIGNATURE_INVALID, ['message' => $e->getMessage()]);
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

    /**
     * @throws GuzzleException
     */
    private function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        if ($this->token) {
            $options['headers'][] = [
                'Authorization' => "Bearer $this->token",
            ];
        }

        return $this->client->request($method, $uri, $options);
    }

    /**
     * Decode the error id from the body of a request from an ApiError.
     */
    private static function getErrorIdFromApiError(string $body): string
    {
        try {
            $jsonData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '';
        }

        // We switched to using relay:errorId in the response body, but some services still use the old format.
        return $jsonData['relay:errorId'] ?? $jsonData['errorId'] ?? '';
    }
}
