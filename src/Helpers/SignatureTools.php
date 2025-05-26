<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Helpers;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use GuzzleHttp\Psr7\Utils;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Http\Message\StreamInterface;

class SignatureTools
{
    private const BLOB_FILES_PATH = '/blob/files';

    /**
     * Create a JWS token.
     *
     * @param string $secret  to create the (symmetric) JWK from
     * @param array  $payload to create the token from
     */
    public static function createSignature(string $secret, array $payload): string
    {
        $jwk = self::createJWK($secret);

        return self::generateToken($jwk, $payload);
    }

    /**
     * Verify a JWS token.
     *
     * @param string $secret to create the (symmetric) JWK from
     * @param string $token  to verify
     *
     * @return array extracted payload from token
     *
     * @throws BlobApiError
     */
    public static function verifySignature(string $secret, string $token): array
    {
        $jwk = self::createJWK($secret);
        $payload = [];

        if (!SignatureTools::verifyToken($jwk, $token, $payload)) {
            /* @noinspection ForgottenDebugOutputInspection */
            // dump(['token' => $token, 'payload' => $payload, 'secret' => $secret]);
            throw new BlobApiError('Invalid signature', BlobApiError::INVALID_SIGNATURE);
        }

        return $payload;
    }

    /**
     * @param StreamInterface|string $data
     */
    public static function generateSha256Checksum(mixed $data): string
    {
        $algorithm = 'sha256';

        if (is_string($data)) {
            return hash($algorithm, $data);
        } elseif ($data instanceof StreamInterface) {
            return Utils::hash($data, $algorithm);
        } else {
            throw new \InvalidArgumentException('generateSha256Checksum: Unsupported data type.');
        }
    }

    /**
     * @throws BlobApiError
     */
    public static function createSignedUrl(string $bucketIdentifier, string $bucketKey,
        string $method, ?string $blobBaseUrl = '', ?string $identifier = null, ?string $action = null,
        array $parameters = [], array $options = []): string
    {
        return self::createSignedUrlFromQueryParameters($bucketKey, $blobBaseUrl,
            self::createQueryParameters($bucketIdentifier, $method, $parameters, $options),
            $identifier, $action);
    }

    /**
     * Create the JWK from a shared secret.
     *
     * @param string $secret to create the (symmetric) JWK from
     */
    private static function createJWK(string $secret): JWK
    {
        return JWKFactory::createFromSecret(
            $secret,
            [
                'alg' => 'HS256',
                'use' => 'sig',
            ]
        );
    }

    /**
     * Generate the token.
     *
     * @param JWK   $jwk     json web key
     * @param array $payload as json string to secure
     *
     * @return string secure token
     */
    private static function generateToken(JWK $jwk, array $payload): string
    {
        $algorithmManager = new AlgorithmManager([new HS256()]);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        try {
            $jws = $jwsBuilder
                ->create()
                ->withPayload(json_encode($payload, JSON_THROW_ON_ERROR))
                ->addSignature($jwk, ['alg' => 'HS256'])
                ->build();
        } catch (\JsonException $e) {
            throw new \RuntimeException('Payload could not be encoded: '.$e->getMessage());
        }

        return (new CompactSerializer())->serialize($jws, 0);
    }

    /**
     * Verify a JWS token.
     *
     * @param string $token   the JWS token as string
     * @param array  $payload to extract from token on success
     *
     * @throws BlobApiError
     */
    private static function verifyToken(JWK $jwk, string $token, array &$payload): bool
    {
        $algorithmManager = new AlgorithmManager([new HS256()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);
        $jws = $serializerManager->unserialize($token);

        if ($ok = $jwsVerifier->verifyWithKey($jws, $jwk, 0)) {
            try {
                $payload = json_decode($jws->getPayload(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new BlobApiError('JWS payload is not valid JSON', BlobApiError::INVALID_SIGNATURE);
            }
        }

        return $ok;
    }

    /**
     * @throws BlobApiError
     */
    private static function createSignedUrlFromQueryParameters(string $bucketKey, string $blobBaseUrl,
        array $queryParameters, ?string $identifier = null, ?string $action = null): string
    {
        $path = self::BLOB_FILES_PATH;
        if ($identifier !== null) {
            $path .= '/'.urlencode($identifier);
        }
        if ($action !== null) {
            $path .= '/'.urlencode($action);
        }

        $pathAndQuery = $path.'?'.http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986);
        $payload = [
            'ucs' => self::generateSha256Checksum($pathAndQuery),
        ];

        try {
            $signature = self::createSignature($bucketKey, $payload);
        } catch (\Exception) {
            throw new BlobApiError('Blob request could not be signed', BlobApiError::CREATING_SIGNATURE_FAILED);
        }

        return $blobBaseUrl.$pathAndQuery.'&sig='.$signature;
    }

    private static function createQueryParameters(string $bucketIdentifier,
        string $method, array $parameters = [], array $options = []): array
    {
        $queryParameters = $parameters;
        $queryParameters['bucketIdentifier'] = $bucketIdentifier;
        $queryParameters['creationTime'] = date('c');
        $queryParameters['method'] = $method;

        if (BlobApi::getIncludeDeleteAt($options)) {
            $queryParameters['includeDeleteAt'] = '1';
        }
        if (BlobApi::getIncludeFileContents($options)) {
            $queryParameters['includeData'] = '1';
        }
        if ($deleteIn = BlobApi::getDeleteIn($options)) {
            $queryParameters['deleteIn'] = $deleteIn;
        }
        if ($prefix = BlobApi::getPrefix($options)) {
            $queryParameters['prefix'] = $prefix;
        }
        if (BlobApi::getPrefixStartsWith($options)) {
            $queryParameters['startsWith'] = '1';
        }
        ksort($queryParameters);

        return $queryParameters;
    }
}
