<?php

declare(strict_types=1);
// require autoload of the correct directory
require __DIR__.'/../vendor/autoload.php';
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;

// default parameters for blobApi
$blobBaseUrl = 'http://127.0.0.1:8000';
$blobBucketId = 'test-bucket';
$blobKey = '';

// define bucketID, prefix and filename
$bucketID = $blobBucketId;
$prefix = 'myData';
$fileName = 'myFile.txt';

// oauth specific variables
// replace with your own config

$oauthIDPUrl = ''; // oauthIDP url including realm
$clientID = '';
$clientSecret = '';

// generate blobApi instance
$blobApi = new BlobApi($blobBaseUrl, $blobBucketId, $blobKey);

// get OAuth2 token
try {
    $blobApi->setOAuth2Token($oauthIDPUrl, $clientID, $clientSecret);
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong while setting the OAuth2 token!', 'blob-library-example:get-token-error', ['message' => $e->getMessage()]);
}

// without additional metadata

// try to upload file, if successful it will return the blob id of the resource
try {
    $id = $blobApi->uploadFile($prefix, $fileName, file_get_contents('myFile.txt'));
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}
echo $id."\n";

// try to download file using the given blob id
try {
    $content = $blobApi->downloadFileAsContentUrlByIdentifier($id);
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// strip base64 prefix with file information from real b64 content, and decode it
echo base64_decode(explode(',', $content)[1], true)."\n";

// with additionalMetadata

// define some additional Metadata
$additionalMetadata = '{"some-key": "some-value"}';

// try to upload file with additional metadata, if successful it will return the blob id of the resource
try {
    $id = $blobApi->uploadFile($prefix, $fileName, file_get_contents('myFile.txt'), $additionalMetadata);
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// try to download file using the given blob id
try {
    $content = $blobApi->getFileDataByIdentifier($id, 1);
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}
// print response as json
echo json_encode($content)."\n";

// try to download file using the given blob id
try {
    $content = $blobApi->getFileDataByPrefix($prefix, 1);
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// print response as json
echo json_encode($content)."\n";

// try to put file using the given blob id
try {
    $content = $blobApi->patchFileByIdentifier($id, 'newFIleName.txt');
    // print response as json
    echo json_encode($content)."\n";
    // get file to see if filename changed
    $content = $blobApi->getFileDataByIdentifier($id, 1);
    // print response as json
    echo json_encode($content)."\n";
} catch (BlobApiError $e) {
    echo $e->getMessage()."\n";
    throw new BlobApiError('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}
