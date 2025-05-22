<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Symfony\Component\HttpFoundation\Response;

interface BlobFileApiInterface
{
    /**
     * @throws BlobApiError
     */
    public function addFile(string $bucketIdentifier, BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    public function updateFile(string $bucketIdentifier, BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $bucketIdentifier, string $identifier, array $options = []): void;

    /**
     * @throws BlobApiError
     */
    public function removeFiles(string $bucketIdentifier, array $options = []): void;

    /**
     * @throws BlobApiError
     */
    public function getFile(string $bucketIdentifier, string $identifier, array $options = []): BlobFile;

    /**
     * @return BlobFile[]
     *
     * @throws BlobApiError
     */
    public function getFiles(string $bucketIdentifier, int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array;

    /**
     * @throws BlobApiError
     */
    public function getFileResponse(string $bucketIdentifier, string $identifier, array $options = []): Response;

    /**
     * @throws BlobApiError
     */
    public function createSignedUrl(string $bucketIdentifier, string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string;
}
