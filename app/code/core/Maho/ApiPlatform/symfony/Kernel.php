<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform;

use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Discovery\ModuleApiDiscovery;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private bool $moduleAutoloaderRegistered = false;

    public function __construct(string $environment = 'prod', bool $debug = false)
    {
        parent::__construct($environment, $debug);
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return BP . '/var/cache/api_platform/' . $this->environment;
    }

    #[\Override]
    public function getLogDir(): string
    {
        return BP . '/var/log';
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \ApiPlatform\Symfony\Bundle\ApiPlatformBundle(),
            new \Nelmio\CorsBundle\NelmioCorsBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => '%env(APP_SECRET)%',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'serializer' => ['enabled' => true],
            'property_access' => ['enabled' => true],
            'property_info' => ['enabled' => true],
            'validation' => ['enabled' => true],
        ]);

        $container->extension('api_platform', [
            'title' => 'Maho Commerce API',
            'version' => '2.0.0',
            'description' => 'Modern REST and GraphQL API for Maho Commerce',
            'enable_swagger_ui' => true,
            'enable_re_doc' => true,
            'enable_entrypoint' => true,
            'enable_docs' => true,
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'docs_formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
                'html' => ['text/html'],
            ],
            'defaults' => [
                'pagination_enabled' => true,
                'pagination_items_per_page' => 20,
                'pagination_maximum_items_per_page' => 100,
                'cache_headers' => [
                    'vary' => ['Accept', 'Authorization'],
                ],
            ],
            'graphql' => [
                'enabled' => true,
                'graphiql' => ['enabled' => true],
                'introspection' => ['enabled' => true],
                'max_query_depth' => 12,
                'max_query_complexity' => 500,
            ],
            'swagger' => [
                'versions' => [3],
                'api_keys' => [
                    'Bearer' => [
                        'name' => 'Authorization',
                        'type' => 'header',
                    ],
                ],
            ],
            'mapping' => [
                'paths' => ['%kernel.project_dir%/ApiResource'],
            ],
            'patch_formats' => [
                'json' => ['application/merge-patch+json'],
            ],
        ]);

        $container->extension('nelmio_cors', [
            'defaults' => [
                'origin_regex' => false,
                'allow_origin' => ['%env(CORS_ALLOW_ORIGIN)%'],
                'allow_methods' => ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'allow_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'expose_headers' => ['Link', 'Deprecation', 'Sunset'],
                'max_age' => 3600,
            ],
            'paths' => [
                '^/api/' => [
                    'allow_origin' => ['%env(CORS_ALLOW_ORIGIN)%'],
                    'allow_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Store-Code'],
                    'allow_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'max_age' => 3600,
                ],
            ],
        ]);

        $container->extension('security', [
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER', 'ROLE_POS', 'ROLE_API_USER'],
                'ROLE_POS' => ['ROLE_USER'],
            ],
            'password_hashers' => [
                \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class => 'auto',
            ],
            'providers' => [
                'maho_customer' => [
                    'id' => \Maho\ApiPlatform\Security\CustomerUserProvider::class,
                ],
                'maho_admin' => [
                    'id' => \Maho\ApiPlatform\Security\AdminUserProvider::class,
                ],
                'maho_chain' => [
                    'chain' => [
                        'providers' => ['maho_admin', 'maho_customer'],
                    ],
                ],
            ],
            'firewalls' => [
                'dev' => [
                    'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                    'security' => false,
                ],
                'api_docs' => [
                    'pattern' => '^/api/docs',
                    'security' => false,
                ],
                'api_admin_graphql' => [
                    'pattern' => '^/api/admin/graphql',
                    'stateless' => true,
                    'provider' => 'maho_admin',
                    'custom_authenticators' => [
                        \Maho\ApiPlatform\Security\AdminSessionAuthenticator::class,
                    ],
                ],
                'api_graphql' => [
                    'pattern' => '^/api/graphql',
                    'stateless' => true,
                    'provider' => 'maho_customer',
                    'custom_authenticators' => [
                        \Maho\ApiPlatform\Security\OAuth2Authenticator::class,
                    ],
                ],
                'api' => [
                    'pattern' => '^/api',
                    'stateless' => true,
                    'provider' => 'maho_chain',
                    'custom_authenticators' => [
                        \Maho\ApiPlatform\Security\OAuth2Authenticator::class,
                    ],
                ],
            ],
            'access_control' => [
                ['path' => '^/api/docs', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/graphql', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/admin/graphql', 'roles' => 'IS_AUTHENTICATED_FULLY'],
                ['path' => '^/api', 'roles' => 'PUBLIC_ACCESS'],
            ],
        ]);

        $services = $container->services();

        $services->defaults()
            ->autowire()
            ->autoconfigure()
            ->private();

        $services->load('Maho\\ApiPlatform\\', '%kernel.project_dir%/')
            ->exclude(['%kernel.project_dir%/Kernel.php', '%kernel.project_dir%/config/']);

        $services->set(EventListener\ApiExceptionListener::class)
            ->arg('$debug', '%kernel.debug%')
            ->tag('kernel.event_subscriber');

        $services->set(GraphQl\CustomQueryResolver::class)
            ->arg('$providerLocator', tagged_locator('maho.api.state_provider'))
            ->tag('api_platform.graphql.query_resolver');

        $services->set(EventListener\DefaultDenyListener::class)
            ->arg('$resourceMetadataFactory', new Reference('api_platform.metadata.resource.metadata_collection_factory'))
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 28]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix('/api');
        // Controller routes loaded via Maho routing, not Symfony attributes
        // $routes->import('%kernel.project_dir%/Controller/', 'attribute');
    }

    /**
     * Boot the kernel — register module autoloader on every request.
     *
     * build() only runs during container compilation. The SPL autoloader
     * must be registered on every request so the Serializer can find
     * module API resource classes when deserializing.
     */
    #[\Override]
    public function boot(): void
    {
        $discovery = ModuleApiDiscovery::discover();
        $this->registerModuleAutoloader($discovery['namespaces']);

        parent::boot();
    }

    /**
     * Register module API resource paths and services.
     *
     * Called only during container compilation. Services must be registered
     * here (not in a compiler pass) so API Platform's own compilation
     * passes can see them when building service locators.
     */
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        $discovery = ModuleApiDiscovery::discover();

        // Inject module Resource paths into API Platform mapping
        if (!empty($discovery['paths'])) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => [
                    'paths' => $discovery['paths'],
                ],
            ]);
        }

        // Register module services
        foreach ($discovery['namespaces'] as $namespace => $baseDir) {
            $this->registerModuleServices($container, $namespace, $baseDir);
        }
    }

    /**
     * Scan a module's Api directory and register all classes as services.
     *
     * Uses require_once to load files directly (the SPL autoloader from
     * boot() isn't reliable during container compilation since class_exists()
     * may fail to resolve cross-dependencies between module classes).
     */
    private function registerModuleServices(ContainerBuilder $container, string $namespace, string $baseDir): void
    {
        if (!is_dir($baseDir)) {
            return;
        }

        // Phase 1: Collect all PHP files and their class names
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($baseDir) + 1);
            $class = $namespace . str_replace('/', '\\', substr($relativePath, 0, -4));
            $files[$class] = $file->getPathname();
        }

        // Phase 2: Load all files
        // Sort so DTO classes (no Provider/Processor suffix) load before Provider/Processor classes
        uksort($files, function ($a, $b) {
            $aIsHandler = str_ends_with($a, 'Provider') || str_ends_with($a, 'Processor');
            $bIsHandler = str_ends_with($b, 'Provider') || str_ends_with($b, 'Processor');
            if (!$aIsHandler && $bIsHandler) {
                return -1;
            }
            if ($aIsHandler && !$bIsHandler) {
                return 1;
            }
            return strcmp($a, $b);
        });

        foreach ($files as $class => $filePath) {
            if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
                try {
                    require_once $filePath;
                } catch (\Throwable $e) {
                    \Mage::log('ApiPlatform: failed to load ' . $filePath . ': ' . $e->getMessage(), \Mage::LOG_ERROR);
                    continue;
                }
            }
        }

        // Phase 3: Register all loaded classes as services
        foreach (array_keys($files) as $class) {
            if (!class_exists($class, false)) {
                continue;
            }

            if ($container->hasDefinition($class)) {
                continue;
            }

            $definition = new Definition($class);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);

            $ref = new \ReflectionClass($class);

            // Skip abstract classes and interfaces
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            // Tag providers for CustomQueryResolver's tagged locator
            if ($ref->implementsInterface(ProviderInterface::class)) {
                $definition->addTag('maho.api.state_provider', ['key' => $class]);
            }

            $container->setDefinition($class, $definition);
        }
    }

    /**
     * Register a PSR-4 autoloader for module Api namespaces.
     * This allows zero-config deployment — no composer dumpautoload needed.
     *
     * @param array<string, string> $namespaces
     */
    private function registerModuleAutoloader(array $namespaces): void
    {
        if (empty($namespaces) || $this->moduleAutoloaderRegistered) {
            return;
        }

        spl_autoload_register(function (string $class) use ($namespaces): void {
            foreach ($namespaces as $prefix => $baseDir) {
                $prefixLen = strlen($prefix);
                if (strncmp($prefix, $class, $prefixLen) === 0) {
                    $relativeClass = substr($class, $prefixLen);
                    $file = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';
                    if (file_exists($file)) {
                        require $file;
                        return;
                    }
                }
            }
        });

        $this->moduleAutoloaderRegistered = true;
    }
}
