<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

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
     * @return iterable<BlobFile>
     *
     * @throws BlobApiError
     */
    public function getFiles(string $bucketIdentifier, int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): iterable;

    /**
     * @throws BlobApiError
     */
    public function getFileStream(string $bucketIdentifier, string $identifier, array $options = []): BlobFileStream;

    /**
     * @throws BlobApiError
     */
    public function createSignedUrl(string $bucketIdentifier, string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string;
}
