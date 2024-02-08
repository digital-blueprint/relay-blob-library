<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

class BlobApiError extends \Exception
{
    // BlobApi::uploadFile
    const ERROR_ID_UPLOAD_FILE_FAILED = 'blob-library:upload-file-failed';
    const ERROR_ID_UPLOAD_FILE_TIMEOUT = 'blob-library:upload-file-timeout';
    const ERROR_ID_UPLOAD_FILE_NOT_SAVED = 'blob-library:upload-file-not-saved';
    const ERROR_ID_UPLOAD_FILE_BUCKET_QUOTA_REACHED = 'blob-library:upload-file-bucket-quota-reached';

    const ERROR_ID_PUT_FILE_METHOD_NOT_SUITABLE = 'blob-library:put-file-method-not-suitable';
    const ERROR_ID_PUT_FILE_BUCKET_QUOTA_REACHED = 'blob-library:put-file-bucket-quota-reached';

    // BlobApi::downloadFileAsContentUrlByIdentifier
    const ERROR_ID_DOWNLOAD_FILE_NOT_FOUND = 'blob-library:download-file-not-found';
    const ERROR_ID_DOWNLOAD_FILE_FAILED = 'blob-library:download-file-failed';
    const ERROR_ID_DOWNLOAD_CONTENT_URL_EMPTY = 'blob-library:download-content-url-empty';
    const ERROR_ID_DOWNLOAD_FILE_TIMEOUT = 'blob-library:download-file-timeout';

    // BlobApi::putFileByIdentifier
    const ERROR_ID_PUT_FILE_FAILED = 'blob-library:put-file-failed';
    const ERROR_ID_PUT_FILE_TIMEOUT = 'blob-library:put-file-timeout';

    // BlobApi::deleteFileByIdentifier
    const ERROR_ID_DELETE_FILE_FAILED = 'blob-library:delete-file-failed';
    const ERROR_ID_DELETE_FILE_TIMEOUT = 'blob-library:delete-file-timeout';

    // BlobApi::deleteFilesByPrefix
    const ERROR_ID_DELETE_FILES_FAILED = 'blob-library:delete-files-failed';
    const ERROR_ID_DELETE_FILES_TIMEOUT = 'blob-library:delete-files-timeout';

    // SignatureTools::verify
    const ERROR_ID_INVALID_SIGNATURE = 'blob-library:invalid-signature';

    // General
    const ERROR_ID_JSON_EXCEPTION = 'blob-library:json-exception';
    const ERROR_ID_SIGNATURE_INVALID = 'blob-library:signature-invalid';
    const ERROR_ID_CHECKSUM_INVALID = 'blob-library:checksum-invalid';

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
