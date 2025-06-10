<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Helpers;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Assert;

class TestUtils
{
    /**
     * @param string $url The signed blob URL to validate
     */
    public static function validateSignedUrl(Assert $assert, string $bucketIdentifier, string $bucketKey, string $blobBaseUrl,
        string $url, string $method, ?string $identifier = null,
        ?string $action = null, array $extraQueryParameters = []): void
    {
        $uri = new Uri($url);

        $path = $uri->getPath();
        $assert->assertEquals(
            '/blob/files'.($identifier ? '/'.$identifier : '').($action ? '/'.$action : ''), $path);
        $assert->assertEquals($blobBaseUrl, $uri->getScheme().'://'.$uri->getHost());

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
        $assert->assertEquals(true, in_array($queryParams['creationTime'], [$dateTimeString, $dateTimeMinusOneSecondString], true));

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
            if ($paramName === 'sig') {
                $assert->assertEquals($expectedSig, $paramValue);
            } else {
                $assert->assertEquals($extraQueryParameters[$paramName], $paramValue);
            }
        }
    }
}
