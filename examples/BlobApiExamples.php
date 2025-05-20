<?php

declare(strict_types=1);
// require autoload of the correct directory
require __DIR__.'/../vendor/autoload.php';
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;

// default parameters for blobApi
$blobBaseUrl = 'http://127.0.0.1:8000';
$bucketIdentifier = 'my-bucket';
$bucketKey = 'b3fc39dc89a4106a9c529555067722729f3b52c88dfd071bc9fed61345e62eb3';

$oidcProviderUrl = 'https://auth.tugraz.at/auth/realms/some_realm';
$oidcClientId = 'client-id';
$oidcClientSecret = 'client-secret';

try {
    $blobApi = BlobApi::createHttpModeApi(
        $bucketIdentifier, $bucketKey, $blobBaseUrl,
        true, $oidcProviderUrl, $oidcClientId, $oidcClientSecret);

    // add a file
    $blobFile = new BlobFile();
    $fileName = 'myFile.txt';
    $blobFile->setFilename($fileName);
    $blobFile->setFile(fopen($fileName, 'r'));
    $blobFile->setPrefix('my-prefix');
    $blobFile = $blobApi->addFile($blobFile);

    // get the file
    $blobFile = $blobApi->getFile($blobFile->getIdentifier());

    // get files
    $blobFiles = $blobApi->getFiles();

    // update the file
    $blobFile->setPrefix('new-prefix');
    $blobFile = $blobApi->updateFile($blobFile);

    // download the file
    $response = $blobApi->getFileResponse($blobFile->getIdentifier());
    $outFileHandle = fopen('out.txt', 'w');
    $writeToFileCallback = function (string $buffer) use ($outFileHandle) {
        fwrite($outFileHandle, $buffer);

        return false;
    };

    ob_start($writeToFileCallback, 4096);
    $response->sendContent();
    ob_end_flush();
    fclose($outFileHandle);

    // remove the file
    $blobApi->removeFile($blobFile->getIdentifier());
} catch (BlobApiError $blobApiError) {
    echo 'An error occurred: '.$blobApiError->getMessage()."\n";
    echo 'Error ID: '.$blobApiError->getErrorId()."\n";
    echo 'Blob error ID: '.$blobApiError->getBlobErrorId()."\n";
}
