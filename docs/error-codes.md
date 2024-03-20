## BlobApiError Codes

### BlobApi::uploadFile

| errorId                                         | Description                                             |
|-------------------------------------------------|---------------------------------------------------------|
| `blob-library:upload-file-failed`               | The upload of the file failed                           |
| `blob-library:upload-file-timeout`              | The request is too old and timed out! Please try again. |
| `blob-library:upload-file-not-saved`            | File could not be saved!                                |
| `blob-library:upload-file-bucket-quota-reached` | Bucket quota is reached!                                |

### BlobApi::patchFileByIdentifier

| errorId                                        | Description                                                                                     |
|------------------------------------------------|-------------------------------------------------------------------------------------------------|
| `blob-library:patch-file-failed`               | The upload of the file failed                                                                   |
| `blob-library:patch-file-timeout`              | The request is too old and timed out! Please try again.                                         |
| `blob-library:patch-file-method-not-suitable`  | The given method in url is not the same as the used method! Please try again.                   |
| `blob-library:patch-file-bucket-quota-reached` | The bucket quota of the given bucket is reached! Please try again or contact your bucket owner. |

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

- For more information about the API, see [api.md](./api.md)
- For usage examples, see [examples](../examples/)
