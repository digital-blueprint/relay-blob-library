<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

class BlobApiError extends \Exception
{
    // BlobApi::uploadFile
    public const ERROR_ID_UPLOAD_FILE_FAILED = 'blob-library:upload-file-failed';
    public const ERROR_ID_UPLOAD_FILE_TIMEOUT = 'blob-library:upload-file-timeout';
    public const ERROR_ID_UPLOAD_FILE_NOT_SAVED = 'blob-library:upload-file-not-saved';
    public const ERROR_ID_UPLOAD_FILE_BUCKET_QUOTA_REACHED = 'blob-library:upload-file-bucket-quota-reached';

    public const ERROR_ID_PATCH_FILE_METHOD_NOT_SUITABLE = 'blob-library:patch-file-method-not-suitable';
    public const ERROR_ID_PATCH_FILE_BUCKET_QUOTA_REACHED = 'blob-library:patch-file-bucket-quota-reached';

    // BlobApi::downloadFileAsContentUrlByIdentifier
    public const ERROR_ID_DOWNLOAD_FILE_NOT_FOUND = 'blob-library:download-file-not-found';
    public const ERROR_ID_DOWNLOAD_FILE_FAILED = 'blob-library:download-file-failed';
    public const ERROR_ID_DOWNLOAD_CONTENT_URL_EMPTY = 'blob-library:download-content-url-empty';
    public const ERROR_ID_DOWNLOAD_FILE_TIMEOUT = 'blob-library:download-file-timeout';

    // BlobApi::patchFileByIdentifier
    public const ERROR_ID_PATCH_FILE_FAILED = 'blob-library:patch-file-failed';
    public const ERROR_ID_PATCH_FILE_TIMEOUT = 'blob-library:patch-file-timeout';

    // BlobApi::deleteFileByIdentifier
    public const ERROR_ID_DELETE_FILE_FAILED = 'blob-library:delete-file-failed';
    public const ERROR_ID_DELETE_FILE_TIMEOUT = 'blob-library:delete-file-timeout';

    // BlobApi::deleteFilesByPrefix
    public const ERROR_ID_DELETE_FILES_FAILED = 'blob-library:delete-files-failed';
    public const ERROR_ID_DELETE_FILES_TIMEOUT = 'blob-library:delete-files-timeout';

    // SignatureTools::verify
    public const ERROR_ID_INVALID_SIGNATURE = 'blob-library:invalid-signature';

    // General
    public const ERROR_ID_JSON_EXCEPTION = 'blob-library:json-exception';
    public const ERROR_ID_SIGNATURE_INVALID = 'blob-library:signature-invalid';
    public const ERROR_ID_CHECKSUM_INVALID = 'blob-library:checksum-invalid';

    private $errorId = '';
    private $errorDetails = [];

    public function __construct(string $message = '', string $errorId = '', array $errorDetails = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorId = $errorId;
        $this->errorDetails = $errorDetails;

        parent::__construct($message, $code, $previous);
    }

    public function getErrorId(): string
    {
        return $this->errorId;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }
}
