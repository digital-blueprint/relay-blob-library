<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Symfony\Component\HttpFoundation\Response;

abstract class AbstractBlobFileApi
{
    public function __construct(protected readonly string $bucketIdentifier)
    {
    }

    public function getBucketIdentifier(): string
    {
        return $this->bucketIdentifier;
    }

    /**
     * @throws BlobApiError
     */
    abstract public function addFile(BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    abstract public function updateFile(BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    abstract public function removeFile(string $identifier, array $options = []): void;

    /**
     * @throws BlobApiError
     */
    abstract public function removeFiles(array $options = []): void;

    /**
     * @throws BlobApiError
     */
    abstract public function getFile(string $identifier, array $options = []): BlobFile;

    /**
     * @return BlobFile[]
     *
     * @throws BlobApiError
     */
    abstract public function getFiles(int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array;

    /**
     * @throws BlobApiError
     */
    abstract public function getFileResponse(string $identifier, array $options = []): Response;

    /**
     * @throws BlobApiError
     */
    abstract public function createSignedUrl(string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string;
}
