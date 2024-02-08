<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

class BlobApiError extends \Exception
{
    private $errorId = '';
    private $errorDetails = [];

    public function __construct(string $message = '', string $errorId = '', array $errorDetails = [], int $code = 0, \Throwable $previous = null)
    {
        $this->errorId = $errorId;
        $this->errorDetails = $errorDetails;

        parent::__construct($message, $code, $previous);
    }

    public function getErrorId(): string
    {
        return $this->errorId;
    }

    public function setErrorId(string $errorId): void
    {
        $this->errorId = $errorId;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public function setErrorDetails(array $errorDetails): void
    {
        $this->errorDetails = $errorDetails;
    }
}
