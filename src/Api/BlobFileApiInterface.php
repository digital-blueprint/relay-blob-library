<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Symfony\Component\HttpFoundation\Response;

interface BlobFileApiInterface
{
    public function setBucketIdentifier(string $bucketIdentifier): void;

    /**
     * @throws BlobApiError
     */
    public function addFile(BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    public function updateFile(BlobFile $blobFile, array $options = []): BlobFile;

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $identifier, array $options = []): void;

    /**
     * @throws BlobApiError
     */
    public function removeFiles(array $options = []): void;

    /**
     * @throws BlobApiError
     */
    public function getFile(string $identifier, array $options = []): BlobFile;

    /**
     * @return BlobFile[]
     *
     * @throws BlobApiError
     */
    public function getFiles(int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array;

    /**
     * @throws BlobApiError
     */
    public function getFileResponse(string $identifier, array $options = []): Response;
}
