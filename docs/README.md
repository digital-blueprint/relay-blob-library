# dbp relay blob library

## Installation

```bash
composer require dbp/relay-blob-library
```

## Usage

```php
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Helpers\Error;

// The blob base url is the url of your API server where the relay-blob-bundle is installed
$blobBaseUrl = 'https://api.your.server';

// See https://github.com/digital-blueprint/relay-blob-bundle#configuration for more information about the blob bucket id and key
$blobBucketId = 'your-bucket-id';
$blobKey = 'your-blob-key';

// Create a new BlobApi instance
$blobApi = new BlobApi($blobBaseUrl, $blobBucketId, $blobKey);

$prefix = 'my-prefix';
$fileName = 'my-file-name.pdf';
$fileData = 'my-binary-file-data';

// Upload a file to the blob storage and get the identifier
try {
    $identifier = $blobApi->uploadFile($prefix, $fileName, $fileData);
} catch (Error $e) {
    // Handle error, print $e->getMessage() for more information
}

// Download a file from the blob storage by identifier and get the content url
try {
    // The content url is a data url and looks for example like this:
    // data:application/pdf;base64,JVBERi0xLjUKJbXtrvsKNCAwIG9iago....= 
    $contentUrl = $blobApi->downloadFileAsContentUrlByIdentifier($identifier);
} catch (Error $e) {
    // Handle error, print $e->getMessage() for more information
}

// Delete a file from the blob storage by identifier
try {
    $blobApi->deleteFileByIdentifier($identifier);
} catch (Error $e) {
    // Handle error, print $e->getMessage() for more information
}

// Delete all files from the blob storage by prefix
try {
    $blobApi->deleteFilesByPrefix($prefix);
} catch (Error $e) {
    // Handle error, print $e->getMessage() for more information
}
```

## API
| API                                                                                                                             | Returns | Description                                                                                                                                                        |
|---------------------------------------------------------------------------------------------------------------------------------|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `deleteFileByIdentifier(string $identifier)`                                                                                    | void    | Deletes the blob fileData with the given blob identifier                                                                                                           |
| `deleteFilesByPrefix(string $prefix)`                                                                                           | void    | Deletes the blob fileDatas that have the given blob prefix                                                                                                         |
| `getFileDataByIdentifier(string $identifier, int $includeData = 1)`                                                             | array   | Returns the whole FileData with the given blob identifier. If $includeData=1, then the contentUrl contains the base64 encoded binary file.                         |
| `getFileDataByPrefix(string $prefix, int $includeData = 1)`                                                                     | array   | Returns a collection of FileDatas that were found in the given prefix. If $includeData=1, then the contentUrl contains the base64 encoded binary file.             |
| `downloadFileAsContentUrlByIdentifier(string $identifier)`                                                                      | string  | Returns the base64 encoded fileData with the given blob identifier                                                                                                 |
| `uploadFile(string $prefix, string $fileName, string $fileData, string $additionalMetadata = '', string $additionalType = '')`  | string  | Uploads the given `$fileData` and associates the given data `$prefix`, `$fileName`, `$additionalMetadata` and `$additionalType` to it.                             |
| `putFileByIdentifier(string $identifier, string $fileName = '', string $additionalMetadata = '', string $additionalType = '')`  | string  | Puts given `fileName`, `additionalMetadata` and `additionalType` into the blob resource with the given `identifier`. Returns the `identifier` of the blob resource |

There is also a `.php` file with usage examples in the directory `examples/`.

## Error Codes

### BlobApi::uploadFile

| errorId                                         | Description                                             |
|-------------------------------------------------|---------------------------------------------------------|
| `blob-library:upload-file-failed`               | The upload of the file failed                           |
| `blob-library:upload-file-timeout`              | The request is too old and timed out! Please try again. |
| `blob-library:upload-file-not-saved`            | File could not be saved!                                |
| `blob-library:upload-file-bucket-quota-reached` | Bucket quota is reached!                                |

### BlobApi::downloadFileAsContentUrlByIdentifier

| errorId                                   | Description                                                 |
|-------------------------------------------|-------------------------------------------------------------|
| `blob-library:download-file-not-found`    | The file to download was not found                          |
| `blob-library:download-file-failed`       | The download of the file failed                             |
| `blob-library:download-content-url-empty` | The `contentUrl` attribute of the downloaded file was empty |
| `blob-library:download-file-timeout`      | The request is too old and timed out! Please try again.     |

### BlobApi::deleteFileByIdentifier

| errorId                            | Description                                             |
|------------------------------------|---------------------------------------------------------|
| `blob-library:delete-file-failed`  | Deleting the file failed                                |
| `blob-library:delete-file-timeout` | The request is too old and timed out! Please try again. |

### BlobApi::deleteFilesByPrefix

| errorId                             | Description                                             |
|-------------------------------------|---------------------------------------------------------|
| `blob-library:delete-files-failed`  | Deleting the files failed                               |
| `blob-library:delete-files-timeout` | The request is too old and timed out! Please try again. |

### SignatureTools::verify

| errorId                          | Description                                   |
|----------------------------------|-----------------------------------------------|
| `blob-library:invalid-signature` | The signature was invalid, while verifying it |

### General

| errorId                          | Description                                                                                                                                                     |
|----------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `blob-library:json-exception`    | Internal exception while encoding JSON                                                                                                                          |
| `blob-library:signature-invalid` | Blob returned that the given signature was invalid, which indicates that either a wrong key was used or that something went wrong while signing                 |
| `blob-library:checksum-invalid`  | Blob returned that the given checksum was invalid, which indicated that either the checksum `ucs` or `bcs` or both were wrong. However, the signature was fine. |
