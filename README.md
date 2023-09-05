# DbpRelayBlobLibrary

[GitHub](https://github.com/digital-blueprint/relay-blob-library) |
[Packagist](https://packagist.org/packages/dbp/relay-blob-library) |
[Changelog](https://github.com/digital-blueprint/relay-blob-library/blob/main/CHANGELOG.md)

[![Test](https://github.com/digital-blueprint/relay-blob-library/actions/workflows/test.yml/badge.svg)](https://github.com/digital-blueprint/relay-blob-library/actions/workflows/test.yml)

PHP helper library for interaction with the [relay-blob-bundle](https://github.com/digital-blueprint/relay-blob-bundle).

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

| errorId                       | Description                            |
|-------------------------------|----------------------------------------|
| `blob-library:json-exception` | Internal exception while encoding JSON |

