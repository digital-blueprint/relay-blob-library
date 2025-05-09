# dbp relay blob library

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
use Dbp\Relay\BlobLibrary\Api\BlobApiError;

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

// oauth specific variables
// replace with your own config

$oauthIDPUrl = 'https://your.oauth.server'; // oauthIDP url including realm
$clientID = 'your-client-id';
$clientSecret = 'your-client-secret';

// if needed, get an OAuth2 token
try {
    $blobApi->setOAuth2Token($oauthIDPUrl, $clientID, $clientSecret);
} catch (BlobApiError $e) {
    // Handle error, print $e->getMessage() for more information
}

// Upload a file to the blob storage and get the identifier
try {
    $identifier = $blobApi->uploadFile($prefix, $fileName, $fileData);
} catch (BlobApiError $e) {
    // Handle error
    var_dump($e->getMessage());
    var_dump($e->getErrorId());
    var_dump($e->getBlobErrorDetails());
}

// Download a file from the blob storage by identifier and get the content url
try {
    // The content url is a data url and looks for example like this:
    // data:application/pdf;base64,JVBERi0xLjUKJbXtrvsKNCAwIG9iago....= 
    $contentUrl = $blobApi->downloadFileAsContentUrlByIdentifier($identifier);
} catch (BlobApiError $e) {
    // Handle error, print $e->getMessage() for more information
}

// Delete a file from the blob storage by identifier
try {
    $blobApi->deleteFileByIdentifier($identifier);
} catch (BlobApiError $e) {
    // Handle error, print $e->getMessage() for more information
}

// Delete all files from the blob storage by prefix
try {
    $blobApi->deleteFilesByPrefix($prefix);
} catch (BlobApiError $e) {
    // Handle error, print $e->getMessage() for more information
}
```

- For more usage examples, see [examples](./examples/)
- For more information about the API, see [api.md](./docs/api.md)
- For information about error codes, see [error-codes.md](./docs/error-codes.md)
