<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Helpers;

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
    /**
     * Create a JWS token.
     *
     * @param string $secret  to create the (symmetric) JWK from
     * @param array  $payload to create the token from
     *
     * @throws \JsonException
     */
    public static function create(string $secret, array $payload): string
    {
        $jwk = self::createJWK($secret);

        return self::generateToken($jwk, $payload);
    }

    /**
     * Create the JWK from a shared secret.
     *
     * @param string $secret to create the (symmetric) JWK from
     */
    public static function createJWK(string $secret): JWK
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
    public static function generateToken(JWK $jwk, array $payload): string
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
    public static function verifyToken(JWK $jwk, string $token, array &$payload): bool
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
     * Verify a JWS token.
     *
     * @param string $secret to create the (symmetric) JWK from
     * @param string $token  to verify
     *
     * @return array extracted payload from token
     *
     * @throws BlobApiError
     */
    public static function verify(string $secret, string $token): array
    {
        $jwk = SignatureTools::createJWK($secret);
        $payload = [];

        if (!SignatureTools::verifyToken($jwk, $token, $payload)) {
            /* @noinspection ForgottenDebugOutputInspection */
            // dump(['token' => $token, 'payload' => $payload, 'secret' => $secret]);
            throw new BlobApiError('Invalid signature', BlobApiError::INVALID_SIGNATURE);
        }

        return $payload;
    }

    /**
     * @param \SplFileInfo|StreamInterface|resource|string $data
     */
    public static function generateSha256Checksum(mixed $data): string
    {
        $algorithm = 'sha256';

        if (is_string($data)) {
            return hash($algorithm, $data);
        } elseif ($data instanceof \SplFileInfo) {
            return hash_file($algorithm, $data->getRealPath());
        } elseif (is_resource($data)) {
            $hashContext = hash_init($algorithm);
            while (feof($data) === false) {
                hash_update($hashContext, fread($data, 1024));
            }
            rewind($data);

            return hash_final($hashContext);
        } elseif ($data instanceof StreamInterface) {
            $stream = Utils::streamFor($data);
            $hashContext = hash_init($algorithm);
            while ($stream->eof() === false) {
                hash_update($hashContext, $stream->read(1024));
            }
            $data->rewind();

            return hash_final($hashContext);
        } else {
            throw new \InvalidArgumentException('generateSha256Checksum: Unsupported data type.');
        }
    }
}
