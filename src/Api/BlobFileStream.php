<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Psr\Http\Message\StreamInterface;

readonly class BlobFileStream
{
    public function __construct(
        private StreamInterface $fileStream,
        private string $fileName,
        private string $mimeType,
        private int $fileSize,
    ) {
    }

    public function getFileStream(): StreamInterface
    {
        return $this->fileStream;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }
}
