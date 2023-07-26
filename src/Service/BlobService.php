<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Service;

use Dbp\Relay\BlobLibrary\Helpers\BlobSignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BlobService
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

    public function __construct(string $blobBaseUrl, string $blobBucketId, $blobKey)
    {
        $this->blobBaseUrl = $blobBaseUrl;
        $this->blobKey = $blobKey;
        $this->blobBucketId = $blobBucketId;
    }

    public function createBlobSignature($payload): string
    {
        try {
            return BlobSignatureTools::create($this->blobKey, $payload);
        } catch (\JsonException $e) {
            throw new \Exception('RequestFile could not be signed for blob storage!', 'dispatch:request-file-blob-signature-failure', ['message' => $e->getMessage()]);
        }
    }

    // TODO: Move to PHP library
    private function generateSha256ChecksumFromUrl($url): string
    {
        return hash('sha256', $url);
    }

    public function deleteBlobFileByRequestFile(RequestFile $requestFile): void
    {
        $blobIdentifier = $requestFile->getFileStorageIdentifier();
        $requestFileIdentifier = $requestFile->getIdentifier();

        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'action' => 'DELETEONE',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $blobIdentifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        $client = new Client();
        try {
            $r = $client->request('DELETE', $url);
        } catch (GuzzleException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be deleted from Blob!', 'dispatch:request-file-blob-delete-error', ['request-file-identifier' => $requestFileIdentifier, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        if ($statusCode !== 204) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be deleted from Blob!', 'dispatch:request-file-blob-delete-error', ['request-file-identifier' => $requestFileIdentifier, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    public function deleteBlobFilesByRequest(Request $request): void
    {
        $dispatchRequestIdentifier = $request->getIdentifier();
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $this->getPrefix($dispatchRequestIdentifier),
            'action' => 'DELETEALL',
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        // We send a DELETE request to the blob service to delete all files with the given prefix,
        // regardless if we have files in dispatch or not, we just want to make sure that the blob files are deleted
        $client = new Client();
        try {
            $r = $client->request('DELETE', $url);
        } catch (GuzzleException $e) {
            // 404 errors are ok, because the files might not exist anymore
            if ($e->getCode() === 404) {
                return;
            }

            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFiles could not be deleted from Blob!', 'dispatch:request-file-blob-delete-error', ['request-identifier' => $dispatchRequestIdentifier, 'message' => $e->getMessage()]);
        }

        $statusCode = $r->getStatusCode();

        // 404 errors are ok, because the files might not exist anymore
        if ($statusCode !== 204 && $statusCode !== 404) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFiles could not be deleted from Blob!', 'dispatch:request-file-blob-delete-error', ['request-identifier' => $dispatchRequestIdentifier, 'message' => 'Blob returned status code '.$statusCode]);
        }
    }

    public function downloadRequestFileAsContentUrl(RequestFile $requestFile): string
    {
        $blobIdentifier = $requestFile->getFileStorageIdentifier();

        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'action' => 'GETONE',
            'binary' => 1,
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams, $blobIdentifier);

        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        $client = new Client();
        try {
            $r = $client->request('GET', $url);
        } catch (GuzzleException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be downloaded from Blob!', 'dispatch:request-file-blob-download-error', ['message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);

        $contentUrl = $jsonData['contentUrl'] ?? '';

        if ($contentUrl === '') {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be downloaded from Blob!', 'dispatch:request-file-blob-download-error', ['message' => 'No contentUrl returned from Blob!']);
        }

        return $contentUrl;
    }

    // TODO: Move to PHP library (in a more general version)
    public function uploadRequestFile(string $dispatchRequestIdentifier, string $fileName, string $fileData): string
    {
        $queryParams = [
            'bucketID' => $this->blobBucketId,
            'creationTime' => date('U'),
            'prefix' => $this->getPrefix($dispatchRequestIdentifier),
            'action' => 'CREATEONE',
            'fileName' => $fileName,
            'fileHash' => hash('sha256', $fileData),
        ];

        $url = $this->getSignedBlobFilesUrl($queryParams);

        // Post to Blob
        // https://github.com/digital-blueprint/relay-blob-bundle/blob/main/doc/api.md
        $client = new Client();
        try {
            $r = $client->request('POST', $url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $fileData,
                        'filename' => $fileName,
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be uploaded to Blob!', 'dispatch:request-file-blob-upload-error', ['message' => $e->getMessage()]);
        }

        $result = $r->getBody()->getContents();
        $jsonData = json_decode($result, true);
        $identifier = $jsonData['identifier'] ?? '';

        if ($identifier === '') {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be uploaded to Blob!', 'dispatch:request-file-blob-upload-error', ['message' => 'No identifier returned from Blob!']);
        }

        // Return the blob file ID
        return $identifier;
    }

    protected function getPrefix(string $dispatchRequestIdentifier): string
    {
        return 'Request/'.$dispatchRequestIdentifier;
    }

    protected function getSignedBlobFilesUrl(array $queryParams, string $blobIdentifier = ''): string
    {
        $path = '/blob/files';

        if ($blobIdentifier !== '') {
            $path .= '/'.urlencode($blobIdentifier);
        }

        // It's mandatory that "%20" is used instead of "+" for spaces in the query string, otherwise the checksum will be invalid!
        $urlPart = $path.'?'.http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $checksum = $this->generateSha256ChecksumFromUrl($urlPart);

        $payload = [
            'cs' => $checksum,
        ];

        $token = $this->createBlobSignature($payload);

        return $this->blobBaseUrl.$urlPart.'&sig='.$token;
    }
}
