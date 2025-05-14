<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Response;

class BlobApiError extends \Exception
{
    public const FILE_NOT_FOUND = 'blob-library:file-not-found';
    public const CONFIGURATION_INVALID = 'blob-library:configuration-invalid';
    public const REQUIRED_PARAMETER_MISSING = 'blob-library:required-parameter-missing';
    public const CONNECT_ERROR = 'blob-library:connect-error';
    public const SERVER_ERROR = 'blob-library:server-error';
    public const CLIENT_ERROR = 'blob-library:client-error';
    public const INVALID_RESPONSE = 'blob-library:invalid-response';
    public const CREATING_SIGNATURE_FAILED = 'blob-library:creating-signature-failed';
    public const INTERNAL_ERROR = 'blob-library:internal-error';
    public const INVALID_SIGNATURE = 'blob-library:invalid-signature';

    public static function createFromRequestException(\Throwable $throwable, string $message): self
    {
        $blobErrorId = null;
        $blobErrorDetails = [];
        $statusCode = null;
        if ($throwable instanceof ConnectException) {
            $errorId = self::CONNECT_ERROR;
        } elseif (($throwable instanceof ClientException || $throwable instanceof ServerException) && $throwable->hasResponse()) {
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

    public function __construct(string $message,
        private readonly string $errorId,
        private readonly ?int $statusCode = null,
        private readonly ?string $blobErrorId = null,
        private readonly array $blobErrorDetails = [],
        ?\Throwable $previous = null)
    {
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
