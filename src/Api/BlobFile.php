<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Psr\Http\Message\StreamInterface;

class BlobFile
{
    /**
     * @param \SplFileInfo|StreamInterface|resource|string|null $file
     */
    public function __construct(
        private array $fileData = [],
        private mixed $file = null,
    ) {
    }

    /**
     * @return \SplFileInfo|StreamInterface|resource|string|null
     */
    public function getFile(): mixed
    {
        return $this->file;
    }

    /**
     * @param \SplFileInfo|StreamInterface|resource|string|null $file
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

    public function setMimeType(?string $mimeType): void
    {
        $this->fileData['mimeType'] = $mimeType;
    }

    public function getMimeType(): ?string
    {
        return $this->fileData['mimeType'] ?? null;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileData['fileSize'] = $fileSize;
    }

    public function getFileSize(): ?int
    {
        return $this->fileData['fileSize'] ?? null;
    }

    public function setFileHash(?string $fileHash): void
    {
        $this->fileData['fileHash'] = $fileHash;
    }

    public function getFileHash(): ?string
    {
        return $this->fileData['fileHash'] ?? null;
    }

    public function setMetadataHash(?string $metadataHash): void
    {
        $this->fileData['metadataHash'] = $metadataHash;
    }

    public function getMetadataHash(): ?string
    {
        return $this->fileData['metadataHash'] ?? null;
    }

    public function setMetadata(string $metadata): void
    {
        $this->fileData['metadata'] = $metadata;
    }

    public function getMetadata(): ?string
    {
        return $this->fileData['metadata'] ?? null;
    }

    public function setContentUrl(?string $contentUrl): void
    {
        $this->fileData['contentUrl'] = $contentUrl;
    }

    public function getContentUrl(): ?string
    {
        return $this->fileData['contentUrl'] ?? null;
    }

    public function setDateCreated(?string $dateCreated): void
    {
        $this->fileData['dateCreated'] = $dateCreated;
    }

    public function getDateCreated(): ?string
    {
        return $this->fileData['dateCreated'] ?? null;
    }

    public function setDateModified(?string $dateModified): void
    {
        $this->fileData['dateModified'] = $dateModified;
    }

    public function getDateModified(): ?string
    {
        return $this->fileData['dateModified'] ?? null;
    }

    public function setDateAccessed(?string $dateAccessed): void
    {
        $this->fileData['dateAccessed'] = $dateAccessed;
    }

    public function getDateAccessed(): ?string
    {
        return $this->fileData['dateAccessed'] ?? null;
    }

    public function setDeleteAt(?string $deleteAt): void
    {
        $this->fileData['deleteAt'] = $deleteAt;
    }

    public function getDeleteAt(): ?string
    {
        return $this->fileData['deleteAt'] ?? null;
    }

    public function setNotifyEmail(?string $notifyEmail): void
    {
        $this->fileData['notifyEmail'] = $notifyEmail;
    }

    public function getNotifyEmail(): ?string
    {
        return $this->fileData['notifyEmail'] ?? null;
    }
}
