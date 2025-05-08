<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Response;

class BlobApiError extends \Exception
{
    public const FILE_NOT_FOUND = 'blob-library:file-not-found';
    public const CONFIGURATION_INVALID = 'blob-library:configuration-invalid';
    public const REQUIRED_PARAMETER_MISSING = 'blob-library:required-parameter-missing';
    public const SERVER_ERROR = 'blob-library:server-error';
    public const CLIENT_ERROR = 'blob-library:client-error';
    public const INVALID_RESPONSE = 'blob-library:invalid-response';
    public const CREATING_SIGNATURE_FAILED = 'blob-library:creating-signature-failed';
    public const INTERNAL_ERROR = 'blob-library:internal-error';
    public const INVALID_SIGNATURE = 'blob-library:invalid-signature';
    public const DEPENDENCY_ERROR = 'blob-library:missing-dependency';

    private ?string $errorId = null;
    private ?string $blobErrorId = null;
    private array $blobErrorDetails = [];
    private ?int $statusCode = null;

    public static function createFromRequestException(\Throwable $throwable, string $message): self
    {
        $blobErrorId = null;
        $blobErrorDetails = [];
        $statusCode = null;
        if (($throwable instanceof ClientException || $throwable instanceof ServerException) && $throwable->hasResponse()) {
            $response = $throwable->getResponse();
            $statusCode = $response->getStatusCode();
            $errorId = $throwable instanceof ClientException ? self::CLIENT_ERROR : self::SERVER_ERROR;
            if ($statusCode === Response::HTTP_NOT_FOUND) {
                $errorId = self::FILE_NOT_FOUND;
            }
            try {
                $errorResponseData = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
                $blobErrorId = $errorResponseData['relay:errorId'] ?? null;
                $blobErrorDetails = $errorResponseData['relay:errorDetails'] ?? [];
            } catch (\JsonException) {
            }
        } else {
            $errorId = self::INTERNAL_ERROR;
        }

        return new self($message, $errorId, $statusCode, $blobErrorId, $blobErrorDetails, $throwable);
    }

    public function __construct(string $message, string $errorId, ?int $statusCode = null,
        ?string $blobErrorId = null, array $blobErrorDetails = [], ?\Throwable $previous = null)
    {
        $this->errorId = $errorId;
        $this->statusCode = $statusCode;
        $this->blobErrorId = $blobErrorId;
        $this->blobErrorDetails = $blobErrorDetails;

        parent::__construct($message, previous: $previous);
    }

    public function getErrorId(): ?string
    {
        return $this->errorId;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getBlobErrorId(): ?string
    {
        return $this->blobErrorId;
    }

    public function getBlobErrorDetails(): array
    {
        return $this->blobErrorDetails;
    }
}
