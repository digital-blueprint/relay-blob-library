# Changelog

## Unreleased

- Add createSignedUrl method
- Always close opened file resources
- Add SignatureTools::createSignedUrl

## v0.3.2

- Complete re-make of the blob library. The actual Blob API access is now wrapped in a BlobFileApiInterface implementation,
where the library provides a built-in HTTP access implementation.
- Add support for custom Blob File API implementations. This allows injecting the `dbp.relay.blob.file_api` service defined 
in the dbp/relay-blob-bundle and talks to Blob directly (via PHP).
- Provide a Symfony config definition tree for integration into the Symfony bundle config
- Make use of authentication (oidc) configurable
- Make sending of file content/metadata checksums configurable
- Add unit tests; also test requests

## 0.2.14

- Dropped support for PHP 8.1
- Dropped support for Psalm
- Added support for web-token/jwt-library v4
- Various dependency fixes

## 0.2.13
- Rename `retentionDuration` to `deleteIn` to conform with the newest blob version

## 0.2.9
- Make function `getSignedBlobFilesUrl` public

## 0.2.8
- Make function `getSignedBlobFilesUrlWithBody` public

## 0.2.7
- Adapt library for changes in blob version v0.1.35

## 0.2.6
- Change Content-Type for `PATCH` requests to `application/merge-patch+json`
  to match the new blob version v0.1.28

## 0.2.5
- Improve error handling in `setOAuth2Token` to only throw a `BlobApiError`

## 0.2.4 
- Adapt library for changes with blob version v0.1.20
  - Change everything from `PUT` to `PATCH`, and the `PATCH` body to `multipart/formdata`.
  - Change `creationTime` from timestamp to ISO 8601 string 
- Implement support for OAuth2 tokens and token refresh

## 0.2.3

- Port from web-token/jwt-core 2.0 to web-token/jwt-library 3.3

## 0.2.2

- Drop support for PHP 7.4/8.0

## 0.2.1

- Drop support for PHP 7.3

## 0.2.0
- There error handling has been improved
  - New error class `Dbp\Relay\BlobLibrary\Api\BlobApiError` with `getMessage`, `getErrorId` and `getErrorDetails` methods
  - There are constants for all error codes

## 0.1.10
- Add support for `relay:errorId` in `\Dbp\Relay\BlobLibrary\Api\BlobApiError::decodeErrorId`

## 0.1.9
- Add function putFileByIdentifier to allow PUT requests to blob
- Add function getFileDataByPrefix to allow GET collection requests of prefixes to blob
- Improve error handling

## 0.1.8

- Adapt PHP API for breaking changes in blob bundle version 0.1.18
- Add function to retrieve blob file metadata
- Add examples/ directory with example usages of the blob API in play php

## 0.1.7

- Adapt PHP API for breaking changes in blob bundle version 0.1.14

## 0.1.6

- Improve error handling and use error codes from the blob bundle
- Add lots of unit tests

## 0.1.5

- Fix error class constructor
- Improve error handling

## 0.1.4

- Add `\Dbp\Relay\BlobLibrary\Helpers\SignatureTools::verify`
- Add `SignatureTools` tests

## 0.1.3

- Extract and add more methods

## 0.1.2

- Refactor `\Dbp\Relay\BlobLibrary\Api\BlobApi::deleteBlobFileByIdentifier` to `\Dbp\Relay\BlobLibrary\Api\BlobApi::deleteFileByIdentifier`
- Refactor `\Dbp\Relay\BlobLibrary\Api\BlobApi::deleteBlobFilesByPrefix` to `\Dbp\Relay\BlobLibrary\Api\BlobApi::deleteFilesByPrefix`

## 0.1.1

- Implement upload, download and deleting of blob files

## 0.1.0

- Initial release
