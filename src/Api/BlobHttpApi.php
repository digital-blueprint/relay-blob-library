<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Dbp\Relay\BlobLibrary\Helpers\SignatureTools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlobHttpApi implements BlobFileApiInterface
{
    private Client $client;
    private ?string $bucketKey = null;
    private ?string $bucketIdentifier = null;
    private bool $oidcEnabled = true;
    private ?string $blobBaseUrl = null;
    private ?string $openIdProviderUrl = null;
    private ?string $clientIdentifier = null;
    private ?string $clientSecret = null;
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

        $this->oidcEnabled = $config['oidc_enabled'] ?? true;
        $this->openIdProviderUrl = $config['oidc_provider_url'] ?? null;
        $this->clientIdentifier = $config['oidc_client_id'] ?? null;
        $this->clientSecret = $config['oidc_client_secret'] ?? null;
        if ($this->oidcEnabled
            && ($this->openIdProviderUrl === null || $this->clientIdentifier === null || $this->clientSecret === null)) {
            throw new BlobApiError('oidc config is invalid', BlobApiError::CONFIGURATION_INVALID);
        }

        $this->token = null;
        $this->timeTokenExpires = 0;

        $this->client = new Client();
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function setBucketIdentifier(string $bucketIdentifier): void
    {
        $this->bucketIdentifier = $bucketIdentifier;
    }

    public function setBlobBaseUrl(string $blobBaseUrl): void
    {
        $this->blobBaseUrl = $blobBaseUrl;
    }

    /**
     * @throws BlobApiError
     */
    public function addFile(BlobFile $blobFile, array $options = []): BlobFile
    {
        if ($blobFile->getFile() === null) {
            throw new BlobApiError('add file: file is required', BlobApiError::REQUIRED_PARAMETER_MISSING);
        }
        if ($blobFile->getFileName() === null) {
            throw new BlobApiError('add file: fileName is required', BlobApiError::REQUIRED_PARAMETER_MISSING);
        }

        return $this->addOrUpdateFile(true, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function updateFile(BlobFile $blobFile, array $options = []): BlobFile
    {
        if ($blobFile->getIdentifier() === null) {
            throw new BlobApiError('update file: identifier is required', BlobApiError::REQUIRED_PARAMETER_MISSING);
        }

        return $this->addOrUpdateFile(false, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $identifier, array $options = []): void
    {
        try {
            $this->request('DELETE', $this->generateUrl('DELETE', [], $options, $identifier));
        } catch (\Throwable $exception) {
            throw BlobApiError::createFromRequestException($exception, 'Removing file failed');
        }
    }

    /**
     * @throws BlobApiError
     */
    public function removeFiles(array $options = []): void
    {
        // TODO: filtering
        $currentPage = 1;
        $maxNumItemsPerPage = 100;
        $files = [];
        do {
            $filePage = $this->getFiles($currentPage, $maxNumItemsPerPage, $options);
            array_push($files, ...$filePage);
            ++$currentPage;
        } while (count($filePage) === $maxNumItemsPerPage);

        foreach ($files as $file) {
            try {
                $this->removeFile($file->getIdentifier());
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
    public function getFile(string $identifier, array $options = []): BlobFile
    {
        try {
            $url = $this->generateUrl('GET', [], $options, $identifier);
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
    public function getFiles(int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array
    {
        $parameters = [
            'page' => $currentPage,
            'perPage' => $maxNumItemsPerPage,
        ];

        // TODO: replace by filter
        if ($prefix = ($options['prefix'] ?? null)) {
            $parameters['prefix'] = $prefix;
        }
        if ($options['startsWith'] ?? false) {
            $parameters['startsWith'] = '1';
        }

        $url = $this->generateUrl('GET', $parameters, $options);
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

    public function getFileResponse(string $identifier, array $options = []): Response
    {
        $url = $this->generateUrl('GET', [], $options, $identifier, 'download');

        return new StreamedResponse(function () use ($url) {
            try {
                $response = $this->client->request('GET', $url, [RequestOptions::STREAM => true]);
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
    private function addOrUpdateFile(bool $isAdd, BlobFile $blobFile, array $options = []): BlobFile
    {
        $method = $isAdd ? 'POST' : 'PATCH';

        $parameters = [];
        if ($prefix = $blobFile->getPrefix()) {
            $parameters['prefix'] = $prefix;
        }
        if ($type = $blobFile->getType()) {
            $parameters['type'] = $type;
        }

        $url = $this->generateUrl($method, $parameters, $options, $isAdd ? null : $blobFile->getIdentifier());

        $multipart = [];
        if ($blobFile->getFile()) {
            $multipart[] = [
                'name' => 'file',
                'contents' => $blobFile->getFile(),
                'filename' => $blobFile->getFileName() ?? '',
            ];
            // TODO: hash stream as well?
            if (is_string($blobFile->getFile())) {
                $multipart[] = [
                    'name' => 'fileHash',
                    'contents' => SignatureTools::generateSha256Checksum($blobFile->getFile()),
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
    }

    private function getQueryParameters(string $method, array $parameters = [], array $options = []): array
    {
        $queryParameters = $parameters;
        $queryParameters['bucketIdentifier'] = $this->bucketIdentifier;
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

        ksort($queryParameters);

        return $queryParameters;
    }

    /**
     * @throws BlobApiError
     */
    private function generateUrl(string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $appendix = null): string
    {
        return $this->generateUrlFromQueryParameters(
            $this->getQueryParameters($method, $parameters, $options),
            $identifier, $appendix);
    }

    /**
     * @throws BlobApiError
     */
    private function generateUrlFromQueryParameters(array $queryParameters, ?string $identifier = null,
        ?string $appendix = null): string
    {
        $path = '/blob/files';
        if ($identifier !== null) {
            $path .= '/'.urlencode($identifier);
        }
        if ($appendix !== null) {
            $path .= '/'.urlencode($appendix);
        }

        // It's mandatory that "%20" is used instead of "+" for spaces in the query string, otherwise the checksum will be invalid!
        $urlPart = $path.'?'.http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986);
        $checksum = SignatureTools::generateSha256Checksum($urlPart);
        $payload = [
            'ucs' => $checksum,
        ];

        return $this->blobBaseUrl.$urlPart.'&sig='.$this->createSignature($payload);
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
    private function createSignature(array $payload): string
    {
        try {
            return SignatureTools::create($this->bucketKey, $payload);
        } catch (\Exception) {
            throw new BlobApiError('Blob request could not be signed', BlobApiError::CREATING_SIGNATURE_FAILED);
        }
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
