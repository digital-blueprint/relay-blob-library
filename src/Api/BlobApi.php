<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class BlobApi
{
    public const DBP_RELAY_BLOB_FILE_API_SERVICE_ALIAS = 'dbp.relay.blob.file_api';

    public const INCLUDE_DELETE_AT_OPTION = 'includeDeleteAt';
    public const INCLUDE_FILE_CONTENTS_OPTION = 'includeData';

    public const DELETE_IN_OPTION = 'deleteIn';
    public const DISABLE_OUTPUT_VALIDATION_OPTION = 'disableOutputValidation';

    /**
     * @deprecated
     */
    public const PREFIX_STARTS_WITH_OPTION = 'startsWith';

    /**
     * @deprecated
     */
    public const PREFIX_OPTION = 'prefix';

    public static function getConfigNodeDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('blob_library');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('use_http_mode')
                    ->info('Whether to use the HTTP mode, i.e. the Blob HTTP (REST) API. If false, a custom Blob API implementation will be used.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('custom_file_api_service')
                    ->info('The fully qualified name or alias of the service to use as custom Blob API implementation. Default is the PHP Blob File API, which comes with the Relay Blob bundle and talks to Blob directly over PHP.')
                    ->defaultValue(self::DBP_RELAY_BLOB_FILE_API_SERVICE_ALIAS)
                ->end()
                ->scalarNode('bucket_identifier')
                    ->info('The identifier of the Blob bucket')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('http_mode')
                    ->children()
                        ->scalarNode('bucket_key')
                        ->info('The signature key of the Blob bucket. Required for HTTP mode.')
                        ->end()
                        ->scalarNode('blob_base_url')
                        ->info('The base URL of the HTTP Blob API. Required for HTTP mode.')
                        ->end()
                        ->scalarNode('oidc_enabled')
                        ->info('Whether to use OpenID connect authentication. Optional for HTTP mode.')
                        ->defaultTrue()
                        ->end()
                        ->scalarNode('oidc_provider_url')
                        ->info('Required for HTTP mode when oidc_enabled is true.')
                        ->end()
                        ->scalarNode('oidc_client_id')
                        ->info('Required for HTTP mode when oidc_enabled is true.')
                        ->end()
                        ->scalarNode('oidc_client_secret')
                        ->info('Required for HTTP mode when oidc_enabled is true.')
                        ->end()
                        ->scalarNode('send_checksums')
                        ->info('Whether to send file content and metadata checksums for Blob to check')
                        ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    /**
     * @throws BlobApiError
     */
    public static function createHttpModeApi(string $bucketIdentifier,
        string $bucketKey, string $blobBaseUrl, bool $oidcEnabled = false,
        ?string $oidcProviderUrl = null, ?string $oidcClientId = null, ?string $oidcClientSecret = null,
        bool $sendChecksums = true): BlobApi
    {
        return BlobApi::createFromConfig([
            'blob_library' => [
                'bucket_identifier' => $bucketIdentifier,
                'use_http_mode' => true,
                'http_mode' => [
                    'bucket_key' => $bucketKey,
                    'blob_base_url' => $blobBaseUrl,
                    'oidc_enabled' => $oidcEnabled,
                    'oidc_provider_url' => $oidcProviderUrl,
                    'oidc_client_id' => $oidcClientId,
                    'oidc_client_secret' => $oidcClientSecret,
                    'send_checksums' => $sendChecksums,
                ],
            ],
        ]);
    }

    public static function createFromBlobFileApi(string $bucketIdentifier, BlobFileApiInterface $blobFileApi): BlobApi
    {
        return new BlobApi($bucketIdentifier, $blobFileApi);
    }

    public static function getCustomModeConfig(string $bucketIdentifier,
        string $customBlobApiServiceOrAlias = BlobApi::DBP_RELAY_BLOB_FILE_API_SERVICE_ALIAS): array
    {
        return [
            'blob_library' => [
                'bucket_identifier' => $bucketIdentifier,
                'use_http_mode' => false,
                'custom_file_api_service' => $customBlobApiServiceOrAlias,
            ],
        ];
    }

    /**
     * @throws BlobApiError
     */
    public static function createFromConfig(array $config, ?ContainerInterface $container = null): BlobApi
    {
        $bucketIdentifier = $config['blob_library']['bucket_identifier'] ?? null;
        if ($bucketIdentifier === null) {
            throw new BlobApiError('blob_library config is invalid: bucket_identifier is required',
                BlobApiError::CONFIGURATION_INVALID);
        }

        $useHttpMode = $config['blob_library']['use_http_mode'] ?? true;
        if ($useHttpMode) {
            $blobFileApiImpl = new HttpFileApi();
            $blobFileApiImpl->setConfig($config['blob_library']['http_mode'] ?? []);
        } else {
            $customBlobApiService = $config['blob_library']['custom_file_api_service'] ?? null;
            if ($customBlobApiService === null) {
                throw new BlobApiError(
                    'blob_library config is invalid: custom_file_api_service is required when \'use_http_mode\' is false',
                    BlobApiError::CONFIGURATION_INVALID);
            }
            if ($container === null) {
                throw new BlobApiError('Container is required when \'use_http_mode\' is true',
                    BlobApiError::CONFIGURATION_INVALID);
            }

            try {
                $blobFileApiImpl = $container->get($customBlobApiService);
            } catch (\Throwable $exception) {
                throw new BlobApiError(
                    'Custom Blob API implementation service or alias not found: '.$exception->getMessage(),
                    BlobApiError::CONFIGURATION_INVALID);
            }
            if (false === $blobFileApiImpl instanceof BlobFileApiInterface) {
                throw new BlobApiError(
                    'Custom Blob API implementation service or alias must implement interface '.
                    BlobFileApiInterface::class, BlobApiError::CONFIGURATION_INVALID);
            }
        }

        return new BlobApi($bucketIdentifier, $blobFileApiImpl);
    }

    public static function setIncludeDeleteAt(array &$options, bool $includeDeleteAt): void
    {
        $options[self::INCLUDE_DELETE_AT_OPTION] = $includeDeleteAt;
    }

    public static function getIncludeDeleteAt(array $options): bool
    {
        return $options[self::INCLUDE_DELETE_AT_OPTION] ?? false;
    }

    public static function setIncludeFileContents(array &$options, bool $includeData): void
    {
        $options[self::INCLUDE_FILE_CONTENTS_OPTION] = $includeData;
    }

    public static function getIncludeFileContents(array $options): bool
    {
        return $options[self::INCLUDE_FILE_CONTENTS_OPTION] ?? false;
    }

    public static function setDeleteIn(array &$options, string $deleteIn): void
    {
        $options[self::DELETE_IN_OPTION] = $deleteIn;
    }

    public static function getDeleteIn(array $options): ?string
    {
        return $options[self::DELETE_IN_OPTION] ?? null;
    }

    /**
     * @deprecated
     */
    public static function setPrefix(array &$options, string $prefix): void
    {
        $options[self::PREFIX_OPTION] = $prefix;
    }

    /**
     * @deprecated
     */
    public static function getPrefix(array $options): ?string
    {
        return $options[self::PREFIX_OPTION] ?? null;
    }

    /**
     * @deprecated
     */
    public static function setPrefixStartsWith(array &$options, bool $prefixStartsWith): void
    {
        $options[self::PREFIX_STARTS_WITH_OPTION] = $prefixStartsWith;
    }

    /**
     * @deprecated
     */
    public static function getPrefixStartsWith(array $options): bool
    {
        return $options[self::PREFIX_STARTS_WITH_OPTION] ?? false;
    }

    public static function setDisableOutputValidation(array &$options, bool $disableOutputValidation): void
    {
        $options[self::DISABLE_OUTPUT_VALIDATION_OPTION] = $disableOutputValidation;
    }

    public static function getDisableOutputValidation(array $options): bool
    {
        return $options[self::DISABLE_OUTPUT_VALIDATION_OPTION] ?? false;
    }

    protected function __construct(
        private readonly string $bucketIdentifier,
        private readonly BlobFileApiInterface $blobFileApiImpl)
    {
    }

    public function getBlobFileApiImpl(): BlobFileApiInterface
    {
        return $this->blobFileApiImpl;
    }

    public function getBucketIdentifier(): string
    {
        return $this->bucketIdentifier;
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

        return $this->blobFileApiImpl->addFile($this->bucketIdentifier, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function updateFile(BlobFile $blobFile, array $options = []): BlobFile
    {
        return $this->blobFileApiImpl->updateFile($this->bucketIdentifier, $blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $identifier, array $options = []): void
    {
        $this->blobFileApiImpl->removeFile($this->bucketIdentifier, $identifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFiles(array $options = []): void
    {
        $this->blobFileApiImpl->removeFiles($this->bucketIdentifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFile(string $identifier, array $options = []): BlobFile
    {
        return $this->blobFileApiImpl->getFile($this->bucketIdentifier, $identifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFiles(int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array
    {
        return $this->blobFileApiImpl->getFiles($this->bucketIdentifier, $currentPage, $maxNumItemsPerPage, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFileResponse(string $identifier, array $options = []): Response
    {
        return $this->blobFileApiImpl->getFileResponse($this->bucketIdentifier, $identifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function createSignedUrl(string $method, array $parameters = [], array $options = [],
        ?string $identifier = null, ?string $action = null): string
    {
        return $this->blobFileApiImpl->createSignedUrl($this->bucketIdentifier, $method, $parameters, $options, $identifier, $action);
    }
}
