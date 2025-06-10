<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Helpers;

use GuzzleHttp\Psr7\Uri;

class TestUtils
{
    /**
     * @param string $url The signed blob URL to validate
     *
     * @throws \Exception
     */
    public static function validateSignedUrl(string $bucketIdentifier, string $bucketKey, string $expectedBlobBaseUrl,
        string $url, string $method, ?string $identifier = null,
        ?string $action = null, array $extraQueryParameters = []): void
    {
        $uri = new Uri($url);

        $path = $uri->getPath();
        $expectedPath = '/blob/files'.($identifier ? '/'.$identifier : '').($action ? '/'.$action : '');
        if ($path !== $expectedPath) {
            throw new \Exception("path '$path' does not match expected '$expectedPath'");
        }
        $blobBaseUrl = $uri->getScheme().'://'.$uri->getHost();
        if ($blobBaseUrl !== $expectedBlobBaseUrl) {
            throw new \Exception("blobBaseUrl '$blobBaseUrl' does not match expected '$expectedBlobBaseUrl'");
        }

        $query = $uri->getQuery();
        $queryParts = explode('&', $query);
        $queryParams = [];
        foreach ($queryParts as $queryParts) {
            $parts = explode('=', $queryParts);
            $queryParams[$parts[0]] = urldecode($parts[1]);
        }

        // consider that the second the url was created might have passed
        $dateTime = new \DateTimeImmutable();
        $dateTimeString = $dateTime->format(\DateTimeInterface::ATOM);
        $dateTimeMinusOneSecondString = $dateTime->modify('-1 second')->format(\DateTimeInterface::ATOM);
        if (false === in_array($queryParams['creationTime'], [$dateTimeString, $dateTimeMinusOneSecondString], true)) {
            throw new \Exception("creationTime '$queryParams[creationTime]' is not as expected");
        }

        $extraQueryParameters = array_merge($extraQueryParameters, [
            'bucketIdentifier' => $bucketIdentifier,
            'method' => $method,
            'creationTime' => $queryParams['creationTime'],  // and use the original creation time for comparison
        ]);

        $query = $uri->getQuery();
        $pos = strrpos($query, '&');
        $queryWithoutSig = substr($query, 0, $pos);

        $url = $path.'?'.$queryWithoutSig;
        $checksum = SignatureTools::generateSha256Checksum($url);
        $payload = [
            'ucs' => $checksum,
        ];
        $expectedSig = SignatureTools::createSignature($bucketKey, $payload);

        foreach ($queryParams as $paramName => $paramValue) {
            $expectedValue = $paramName === 'sig' ? $expectedSig : $extraQueryParameters[$paramName];
            if ($paramValue !== $expectedValue) {
                throw new \Exception("value of query parameter '$paramName' '$paramValue' does not match expected value '$expectedValue'");
            }
        }
    }
}
