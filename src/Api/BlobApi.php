<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Api;

use Symfony\Component\HttpFoundation\Response;

class BlobApi implements BlobFileApiInterface
{
    private const PREFIX_STARTS_WITH_OPTION = 'startsWith';
    private const PREFIX_OPTION = 'prefix';
    private const INCLUDE_DELETE_AT_OPTION = 'include_delete_at';
    private const INCLUDE_DATA_OPTION = 'include_data';
    private const DELETE_IN_OPTION = 'delete_in';

    private ?BlobFileApiInterface $blobFileApiImpl = null;

    public static function getConfigNodeDefinition(): object
    {
        if (class_exists('Symfony\Component\Config\Definition\Builder\TreeBuilder')) {
            $treeBuilder = new \Symfony\Component\Config\Definition\Builder\TreeBuilder('blob_library');
            $rootNode = $treeBuilder->getRootNode();
            $rootNode
                ->children()
                    ->scalarNode('use_http_mode')
                        ->description('Whether to use the HTTP mode, i.e. the Blob HTTP (REST) API. If false, a custom Blob API implementation will be used.')
                        ->defaultTrue()
                    ->end()
                    ->scalarNode('custom_blob_api_service')
                        ->description('The fully qualified name or alias of the service to use as custom Blob API implementation.')
                        ->defaultValue('blob_php_api')
                    ->end()
                    ->scalarNode('bucket_identifier')
                       ->description('The identifier of the Blob bucket')
                       ->isRequired()
                       ->cannotBeEmpty()
                    ->end()
                    ->arrayNode('http_mode')
                        ->scalarNode('bucket_key')
                            ->description('The signature key of the Blob bucket. Required for HTTP mode.')
                        ->end()
                        ->scalarNode('base_url')
                           ->description('The base URL of the HTTP Blob API. Required for HTTP mode.')
                        ->end()
                        ->scalarNode('oidc_enabled. Whether to use OpenID connect authentication. Optional for HTTP mode.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('oidc_provider_url. Required for HTTP mode when oidc_enabled is true.')
                        ->scalarNode('oidc_client_id. Required for HTTP mode when oidc_enabled is true.')
                        ->end()
                        ->scalarNode('oidc_client_secret. Required for HTTP mode when oidc_enabled is true.')
                        ->end()
                    ->end()
                ->end();

            return $rootNode;
        }
        throw new BlobApiError('\Symfony\Component\Config\Definition\Builder\TreeBuilder must be declared to use '.__METHOD__,
            BlobApiError::DEPENDENCY_ERROR);
    }

    /**
     * @throws BlobApiError
     */
    public static function createHttpModeApi(string $bucketIdentifier,
        string $bucketKey, string $blobBaseUrl, bool $oidcEnabled = false,
        ?string $oidcProviderUrl = null, ?string $oidcClientId = null, ?string $oidcClientSecret = null): BlobApi
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
                ],
            ],
        ]);
    }

    /**
     * @throws BlobApiError
     */
    public static function createFromConfig(array $config, ?object $container = null): BlobApi
    {
        $bucketIdentifier = $config['blob_library']['bucket_identifier'] ?? null;
        if ($bucketIdentifier === null) {
            throw new BlobApiError('blob_library config is invalid: bucket_identifier is required',
                BlobApiError::CONFIGURATION_INVALID);
        }

        $useHttpMode = $config['blob_library']['use_http_mode'] ?? true;
        if ($useHttpMode) {
            $blobFileApiImpl = new BlobHttpApi();
            $blobFileApiImpl->setConfig($config['blob_library']['http_mode'] ?? []);
        } else {
            $customBlobApiService = $config['blob_library']['custom_blob_api_service'] ?? null;
            if ($customBlobApiService === null) {
                throw new BlobApiError(
                    'blob_library config is invalid: custom_blob_api_service is required when \'use_http_mode\' is false',
                    BlobApiError::CONFIGURATION_INVALID);
            }
            if ($container === null) {
                throw new BlobApiError('Container is required when \'use_http_mode\' is true',
                    BlobApiError::CONFIGURATION_INVALID);
            }
            //            if (get_class($container) !== '\Symfony\Component\DependencyInjection\Container') {
            //                throw new BlobApiError('parameter \'container\' must of of type \'\Symfony\Component\DependencyInjection\Container\'',
            //                    BlobApiError::DEPENDENCY_ERROR);
            //            }
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
        $blobFileApiImpl->setBucketIdentifier($bucketIdentifier);

        return new BlobApi($blobFileApiImpl);
    }

    public static function setIncludeDeleteAt(array &$options, bool $includeDeleteAt): void
    {
        $options[self::INCLUDE_DELETE_AT_OPTION] = $includeDeleteAt;
    }

    public static function getIncludeDeleteAt(array $options): bool
    {
        return $options[self::INCLUDE_DELETE_AT_OPTION] ?? false;
    }

    public static function setIncludeData(array &$options, bool $includeData): void
    {
        $options[self::INCLUDE_DATA_OPTION] = $includeData;
    }

    public static function getIncludeData(array $options): bool
    {
        return $options[self::INCLUDE_DATA_OPTION] ?? false;
    }

    public static function setDeleteIn(array &$options, string $deleteIn): void
    {
        $options[self::DELETE_IN_OPTION] = $deleteIn;
    }

    public static function getDeleteIn(array $options): ?string
    {
        return $options[self::DELETE_IN_OPTION] ?? null;
    }

    public static function setPrefix(array &$options, string $prefix): void
    {
        $options[self::PREFIX_OPTION] = $prefix;
    }

    public static function getPrefix(array $options): ?string
    {
        return $options[self::PREFIX_OPTION] ?? null;
    }

    public static function setPrefixStartsWith(array &$options, bool $prefixStartsWith): void
    {
        $options[self::PREFIX_STARTS_WITH_OPTION] = $prefixStartsWith;
    }

    public static function getPrefixStartsWith(array $options): bool
    {
        return $options[self::PREFIX_STARTS_WITH_OPTION] ?? false;
    }

    public function __construct(BlobFileApiInterface $blobFileApiImpl)
    {
        $this->blobFileApiImpl = $blobFileApiImpl;
    }

    public function getBlobFileApiImpl(): BlobFileApiInterface
    {
        return $this->blobFileApiImpl;
    }

    public function setBucketIdentifier(string $bucketIdentifier): void
    {
        $this->blobFileApiImpl->setBucketIdentifier($bucketIdentifier);
    }

    /**
     * @throws BlobApiError
     */
    public function addFile(BlobFile $blobFile, array $options = []): BlobFile
    {
        return $this->blobFileApiImpl->addFile($blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function updateFile(BlobFile $blobFile, array $options = []): BlobFile
    {
        return $this->blobFileApiImpl->updateFile($blobFile, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFile(string $identifier, array $options = []): void
    {
        $this->blobFileApiImpl->removeFile($identifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function removeFiles(array $options = []): void
    {
        $this->blobFileApiImpl->removeFiles($options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFile(string $identifier, array $options = []): BlobFile
    {
        return $this->blobFileApiImpl->getFile($identifier, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFiles(int $currentPage = 1, int $maxNumItemsPerPage = 30, array $options = []): array
    {
        return $this->blobFileApiImpl->getFiles($currentPage, $maxNumItemsPerPage, $options);
    }

    /**
     * @throws BlobApiError
     */
    public function getFileResponse(string $identifier, array $options = []): Response
    {
        return $this->blobFileApiImpl->getFileResponse($identifier, $options);
    }
}
