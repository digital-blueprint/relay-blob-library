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

Here is an example of how to use the library in HTTP mode, with OIDC authentication enabled:
```php
    // create the API
    $blobApi = BlobApi::createHttpModeApi(
        $bucketIdentifier, $bucketKey, $blobBaseUrl,
        true /* OIDC enabled */, $oidcProviderUrl, $oidcClientId, $oidcClientSecret);

    $blobFile = new BlobFile();
    $filePath = 'files/myFile.txt';
    $blobFile->setFilename(basename($filePath));
    $blobFile->setFile(new SplFileInfo($filePath));
    $blobFile->setPrefix('my-prefix');
    
    // add the file    
    $blobFile = $blobApi->addFile($blobFile);

    // get the file
    $blobFile = $blobApi->getFile($blobFile->getIdentifier());

    // remove the file
    $blobApi->removeFile($blobFile->getIdentifier());
```

- For the whole example PHP code, see [examples](./examples/)

## Integration into a Symfony bundle

Soon to come.
