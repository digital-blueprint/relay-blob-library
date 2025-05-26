<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpFileApi implements BlobFileApiInterface
{
    private const DOWNLOAD_ACTION = 'download';

    private Client $client;
    private ?string $bucketKey = null;
    private bool $oidcEnabled = true;
    private ?string $blobBaseUrl = null;
    private ?string $openIdProviderUrl = null;
    private ?string $clientIdentifier = null;
    private ?string $clientSecret = null;
    private bool $sendChecksums = true;
    private ?string $token = null;
    private int $timeTokenExpires = 0;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @throws BlobApiError
     */
    public function setConfig(array $config): void
    {
        $this->blobBaseUrl = $config['blob_base_url'] ?? null;
        $this->bucketKey = $config['bucket_key'] ?? null;
        if ($this->blobBaseUrl === null || $this->bucketKey === null) {
            throw new BlobApiError('REST API config is invalid: blob_base_url and bucket_key are required',
                BlobApiError::CONFIGURATION_INVALID);
        }

        $this->oidcEnabled = $config['oidc_enabled'] ?? false;
        $this->openIdProviderUrl = $config['oidc_provider_url'] ?? null;
        $this->clientIdentifier = $config['oidc_client_id'] ?? null;
        $this->clientSecret = $config['oidc_client_secret'] ?? null;
        if ($this->oidcEnabled
            && ($this->openIdProviderUrl === null || $this->clientIdentifier === null || $this->clientSecret === null)) {
            throw new BlobApiError('oidc config is invalid', BlobApiError::CONFIGURATION_INVALID);
        }
        $this->sendChecksums = $config['send_checksums'] ?? true;

        $this->token = null;
        $this->timeTokenExpires = 0;

        $this->client = new Client();
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @throws BlobApiError
     */
    public function addFile(string $bucketIdentifier, BlobFile $blobFile, array $options = []): BlobFile
    {
        return $this->addOrUpdateFile($bucketIdentifier, true, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function updateFile(string $bucketIdentifier, BlobFile $blobFile, array $options = []): BlobFile
    {
        if ($blobFile->getIdentifier() === null) {
            throw new BlobApiError('update file: identifier is required', BlobApiError::REQUIRED_PARAMETER_MISSING);
        }

        return $this->addOrUpdateFile($bucketIdentifier, false, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $bucketIdentifier, string $identifier, array $options = []): void
    {
        try {
            $this->request('DELETE',
                $this->createSignedUrlInternal($bucketIdentifier, 'DELETE', [], $options, $identifier));
        } catch (\Throwable $exception) {
            throw BlobApiError::createFromRequestException($exception, 'Removing file failed');
        }
    }

    /**
     * @throws BlobApiError
     */
    public function removeFiles(string $bucketIdentifier, array $options = []): void
    {
        // TODO: filtering
        $currentPage = 1;
        $maxNumItemsPerPage = 100;
        $files = [];
        do {
            $filePage = $this->getFiles($bucketIdentifier, $currentPage, $maxNumItemsPerPage, $options);
            array_push($files, ...$filePage);
            ++$currentPage;
        } while (count($filePage) === $maxNumItemsPerPage);

        foreach ($files as $file) {
            try {
                $this->removeFile($bucketIdentifier, $file->getIdentifier());
            } catch (BlobApiError $exception) {
                if ($exception->getErrorId() !== BlobApiError::FILE_NOT_FOUND) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @throws BlobApiError
     */
    public function getFile(string $bucketIdentifier, string $identifier, array $options = []): BlobFile
    {
        try {
            $url = $this->createSignedUrlInternal($bucketIdentifier, 'GET', [], $options, $identifier);
            $requestOptions = [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/ld+json',
                ]];

            return $this->createBlobFileFromResponse(
                $this->request('GET', $url, $requestOptions));
        } catch (\Throwable $exception) {
            throw BlobApiError::createFromRequestException($exception, 'Getting file failed');
        }
    }

    /**
     * @throws BlobApiError
     */
    public function getFiles(string $bucketIdentifier, int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array
    {
        $parameters = [
            'page' => $currentPage,
            'perPage' => $maxNumItemsPerPage,
        ];

        $url = $this->createSignedUrlInternal($bucketIdentifier, 'GET', $parameters, $options);
        $requestOptions = [
            RequestOptions::HEADERS => [
                'Accept' => 'application/ld+json',
            ]];

        try {
            $response = $this->request('GET', $url, $requestOptions);
            try {
                $responseDecoded =
                    json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BlobApiError('Response is not valid JSON', BlobApiError::INVALID_RESPONSE);
            }

            $fileDataCollection = [];
            foreach ($responseDecoded['hydra:member'] as $fileData) {
                $fileDataCollection[] = new BlobFile($fileData);
            }

            return $fileDataCollection;
        } catch (\Throwable $exception) {
            throw BlobApiError::createFromRequestException($exception, 'Getting files failed');
        }
    }

    public function getFileResponse(string $bucketIdentifier, string $identifier, array $options = []): Response
    {
        $url = $this->createSignedUrlInternal($bucketIdentifier, 'GET', [], $options, $identifier, self::DOWNLOAD_ACTION);

        return new StreamedResponse(function () use ($url) {
            try {
                $response = $this->request('GET', $url, [RequestOptions::STREAM => true]);
            } catch (\Throwable $exception) {
                throw BlobApiError::createFromRequestException($exception, 'Downloading file failed');
            }
            $body = $response->getBody();
            while (!$body->eof()) {
                echo $body->read(1024);
            }
        });
    }

    /**
     * @throws BlobApiError
     */
    public function createSignedUrl(string $bucketIdentifier, string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string
    {
        return $this->createSignedUrlInternal($bucketIdentifier, $method, $parameters, $options, $identifier, $action);
    }

    /**
     * @throws BlobApiError
     */
    private function addOrUpdateFile(string $bucketIdentifier, bool $isAdd, BlobFile $blobFile, array $options = []): BlobFile
    {
        $method = $isAdd ? 'POST' : 'PATCH';

        $parameters = [];
        if ($prefix = $blobFile->getPrefix()) {
            $parameters['prefix'] = $prefix;
        }
        if ($type = $blobFile->getType()) {
            $parameters['type'] = $type;
        }
        if ($notifyEmail = $blobFile->getNotifyEmail()) {
            $parameters['notifyEmail'] = $notifyEmail;
        }

        $url = $this->createSignedUrlInternal($bucketIdentifier, $method, $parameters, $options,
            $isAdd ? null : $blobFile->getIdentifier());

        $multipart = [];
        $fileHandle = null;

        try {
            if (($file = $blobFile->getFile()) !== null) {
                if ($file instanceof \SplFileInfo) {
                    if ($realPath = $file->getRealPath()) {
                        $fileHandle = fopen($realPath, 'r');
                    }
                    if (false === is_resource($fileHandle)) {
                        throw new BlobApiError('Failed to read input file', BlobApiError::FILE_NOT_READABLE);
                    }
                    $fileResource = $fileHandle;
                } else {
                    $fileResource = $file;
                }
                $fileStream = Utils::streamFor($fileResource);

                $multipart[] = [
                    'name' => 'file',
                    'contents' => $fileStream,
                    'filename' => $blobFile->getFileName() ?? 'unknown',
                ];
                if ($this->sendChecksums) {
                    $multipart[] = [
                        'name' => 'fileHash',
                        'contents' => SignatureTools::generateSha256Checksum($fileStream),
                    ];
                }
            }

            if ($fileName = $blobFile->getFileName()) {
                $multipart[] = [
                    'name' => 'fileName',
                    'contents' => $fileName,
                ];
            }

            if ($metadata = $blobFile->getMetadata()) {
                $multipart[] = [
                    'name' => 'metadata',
                    'contents' => $metadata,
                ];
                if ($this->sendChecksums) {
                    $multipart[] = [
                        'name' => 'metadataHash',
                        'contents' => SignatureTools::generateSha256Checksum($metadata),
                    ];
                }
            }

            $requestOptions = [
                RequestOptions::HEADERS => ['Accept' => 'application/ld+json'],
                RequestOptions::MULTIPART => $multipart,
            ];

            try {
                return $this->createBlobFileFromResponse($this->request($method, $url, $requestOptions));
            } catch (\Throwable $exception) {
                throw BlobApiError::createFromRequestException($exception, $isAdd ?
                    'Adding file failed' : 'Updating file failed');
            }
        } finally {
            if ($fileHandle !== null) {
                fclose($fileHandle);
            }
        }
    }

    /**
     * @throws BlobApiError
     */
    private function createSignedUrlInternal(string $bucketIdentifier, string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string
    {
        return SignatureTools::createSignedUrl($bucketIdentifier, $this->bucketKey,
            $method, $this->blobBaseUrl, $identifier, $action, $parameters, $options);
    }

    /**
     * @throws GuzzleException
     * @throws BlobApiError
     */
    private function request(string $method, string $url, array $requestOptions = []): ResponseInterface
    {
        if ($this->oidcEnabled) {
            $requestOptions[RequestOptions::HEADERS]['Authorization'] = 'Bearer '.$this->getOidcToken();
        }

        return $this->client->request($method, $url, $requestOptions);
    }

    /**
     * @throws BlobApiError
     */
    private function getOidcToken(): string
    {
        if ($this->token === null || time() > $this->timeTokenExpires) {
            try {
                $configBody = (string) $this->client->get($this->openIdProviderUrl.'/.well-known/openid-configuration')->getBody();
                $config = json_decode($configBody, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BlobApiError('Received oidc configuration is not valid json', BlobApiError::INVALID_RESPONSE);
            } catch (GuzzleException $exception) {
                throw BlobApiError::createFromRequestException($exception, 'Getting oidc configuration failed');
            }

            try {
                $response = $this->client->post(
                    $config['token_endpoint'], [
                        'auth' => [$this->clientIdentifier, $this->clientSecret],
                        'form_params' => ['grant_type' => 'client_credentials'],
                    ]);
                $data = (string) $response->getBody();
                $json = json_decode($data, true, flags: JSON_THROW_ON_ERROR);

                $this->token = $json['access_token'];
                $this->timeTokenExpires = time() + ($json['expires_in'] - 20);
            } catch (\JsonException) {
                throw new BlobApiError('Received oidc token payload is not valid json', BlobApiError::INVALID_RESPONSE);
            } catch (GuzzleException $exception) {
                throw BlobApiError::createFromRequestException($exception, 'Getting oidc token failed');
            }
        }

        return $this->token;
    }

    /**
     * @throws BlobApiError
     */
    private function createBlobFileFromResponse(ResponseInterface $response): BlobFile
    {
        try {
            return new BlobFile(
                json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            throw new BlobApiError('Response is not valid JSON', BlobApiError::INVALID_RESPONSE);
        }
    }
}
