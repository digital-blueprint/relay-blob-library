# Changelog

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
