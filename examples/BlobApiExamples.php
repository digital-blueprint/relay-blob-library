<?php

declare(strict_types=1);
// require autoload of the correct directory
require __DIR__.'/../vendor/autoload.php';
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Helpers\Error;

// default parameters for blobApi
$blobBaseUrl = 'http://127.0.0.1:8000';
$blobBucketId = '1234';
$blobKey = '';

// generate blobApi instance
$blobApi = new BlobApi($blobBaseUrl, $blobBucketId, $blobKey);

// define bucketID, prefix and filename
$bucketID = $blobBucketId;
$prefix = 'myData';
$fileName = 'myFile.txt';

// without additional metadata

// try to upload file, if successful it will return the blob id of the resource
try {
    $id = $blobApi->uploadFile($prefix, $fileName, file_get_contents('myFile.txt'));
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// try to download file using the given blob id
try {
    $content = $blobApi->downloadFileAsContentUrlByIdentifier($id);
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// strip base64 prefix with file information from real b64 content, and decode it
echo base64_decode(explode(',', $content)[1], true)."\n";

// with additionalMetadata

// define some additional Metadata
$additionalMetadata = '{"some-key": "some-value"}';

// try to upload file with additional metadata, if successful it will return the blob id of the resource
try {
    $id = $blobApi->uploadFile($prefix, $fileName, file_get_contents('myFile.txt'), $additionalMetadata);
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}

// try to download file using the given blob id
try {
    $content = $blobApi->getFileDataByIdentifier($id, 1);
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}
// print response as json
echo json_encode($content)."\n";

// try to download file using the given blob id
try {
    $content = $blobApi->getFileDataByPrefix($prefix, 1);
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}


// print response as json
echo json_encode($content)."\n";

// try to put file using the given blob id
try {
    $content = $blobApi->putFileByIdentifier($id, 'newFIleName.txt');
    // print response as json
    echo json_encode($content)."\n";
    // get file to see if filename changed
    $content = $blobApi->getFileDataByIdentifier($id, 1);
    // print response as json
    echo json_encode($content)."\n";
} catch (Error $e) {
    echo $e->getMessage()."\n";
    throw Error::withDetails('Something went wrong!', 'blob-library-example:upload-file-error', ['message' => $e->getMessage()]);
}
