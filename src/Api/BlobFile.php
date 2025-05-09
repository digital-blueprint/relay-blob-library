<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Psr\Http\Message\StreamInterface;

class BlobFile
{
    /** @var StreamInterface|resource|string|null */
    private mixed $file = null;
    private array $fileData = [];

    /**
     * @param StreamInterface|resource|string|null $file
     */
    public function __construct(
        array $fileData = [],
        mixed $file = null,
    ) {
        $this->file = $file;
        $this->fileData = $fileData;
    }

    public function getFile(): mixed
    {
        return $this->file;
    }

    /**
     * @param StreamInterface|resource|string|null $file
     */
    public function setFile(mixed $file): void
    {
        $this->file = $file;
    }

    public function setFileData(array $fileData): void
    {
        $this->fileData = $fileData;
    }

    public function getFileData(): array
    {
        return $this->fileData;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->fileData['identifier'] = $identifier;
    }

    public function getIdentifier(): ?string
    {
        return $this->fileData['identifier'] ?? null;
    }

    public function setPrefix(string $prefix): void
    {
        $this->fileData['prefix'] = $prefix;
    }

    public function getPrefix(): ?string
    {
        return $this->fileData['prefix'] ?? null;
    }

    public function setType(string $type): void
    {
        $this->fileData['type'] = $type;
    }

    public function getType(): ?string
    {
        return $this->fileData['type'] ?? null;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileData['fileName'] = $fileName;
    }

    public function getFileName(): ?string
    {
        return $this->fileData['fileName'] ?? null;
    }

    public function setMetadata(string $metadata): void
    {
        $this->fileData['metadata'] = $metadata;
    }

    public function getMetadata(): ?string
    {
        return $this->fileData['metadata'] ?? null;
    }

    public function getContentUrl(): ?string
    {
        return $this->fileData['contentUrl'] ?? null;
    }
}
