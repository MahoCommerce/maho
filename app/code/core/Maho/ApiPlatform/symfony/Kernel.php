<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

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
        $this->resolveEnvironmentVars();
        parent::__construct($environment, $debug);
    }

    /**
     * Resolve env vars from Maho config so they're available when
     * configureContainer() runs `%env(APP_SECRET)%` / `%env(CORS_ALLOW_ORIGIN)%`.
     * Mage must already be initialized before constructing the kernel.
     */
    private function resolveEnvironmentVars(): void
    {
        if (!isset($_ENV['APP_SECRET'])) {
            // Shared with JwtService so the admin/token path and the kernel
            // generate-and-persist the same secret regardless of which boots first.
            $_ENV['APP_SECRET'] = \Maho\ApiPlatform\Service\JwtService::resolveSecret();
        }

        if (!isset($_ENV['CORS_ALLOW_ORIGIN'])) {
            $corsOrigins = (string) \Mage::getStoreConfig('apiplatform/general/cors_origins');
            if ($corsOrigins === '') {
                // Fall back to the store's own origin, but only when base_url
                // parses to a real host. Never synthesize a guessable default
                // like 'localhost'. Failing closed (empty allowlist, no
                // cross-origin access) is safer than trusting a fabricated one.
                $baseUrl = (string) \Mage::getStoreConfig('web/secure/base_url');
                $parsed = parse_url($baseUrl);
                $corsOrigins = empty($parsed['host'])
                    ? ''
                    : ($parsed['scheme'] ?? 'https') . '://' . $parsed['host']
                        . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            }
            // NelmioCors expects an array of origins. Encode as JSON so the
            // env() resolver can split a comma-separated config value into
            // multiple entries instead of treating the whole string as one
            // (broken) origin literal.
            $origins = array_values(array_filter(
                array_map('trim', explode(',', $corsOrigins)),
                static fn(string $o): bool => $o !== '',
            ));
            // Reject `*` in the allowlist. NelmioCors echoes a wildcard origin
            // back to any caller, which combined with allow_credentials creates
            // a CSRF vector, and even without credentials it bypasses the
            // operator's intent of pinning origins. Operators must enumerate
            // the origins they actually trust.
            $origins = array_values(array_filter(
                $origins,
                static fn(string $o): bool => $o !== '*',
            ));
            if (in_array('*', explode(',', $corsOrigins), true)) {
                \Mage::log(
                    'apiplatform/general/cors_origins contains a "*" wildcard; '
                    . 'wildcards are dropped, list explicit origins instead.',
                    \Mage::LOG_WARNING,
                );
            }
            $_ENV['CORS_ALLOW_ORIGIN'] = json_encode($origins, JSON_THROW_ON_ERROR);
        }
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * Symfony writes its compiled container/route/metadata cache here on first
     * request. The directory must be writable by the web user; deployment
     * scripts should pre-warm it (see docs/API.md → Deployment Notes) so the
     * first /api/* request after a release doesn't pay container-compile cost.
     */
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
        $bundles = [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \ApiPlatform\Symfony\Bundle\ApiPlatformBundle(),
            new \Nelmio\CorsBundle\NelmioCorsBundle(),
        ];

        // symfony/twig-bundle is a `suggest`-only dependency. Inlining the
        // class_exists guard (rather than hiding it behind a helper) lets
        // PHPStan narrow the type and accept the `new` below.
        if (class_exists(\Symfony\Bundle\TwigBundle\TwigBundle::class)) {
            $bundles[] = new \Symfony\Bundle\TwigBundle\TwigBundle();
        }

        return $bundles;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        // Decode CORS_ALLOW_ORIGIN here rather than relying on Symfony's
        // %env(json:...)% resolver. Extension load() callbacks (e.g.
        // NelmioCorsExtension) run before env placeholders are expanded,
        // so the placeholder string reaches in_array() unresolved and
        // trips a TypeError. resolveEnvironmentVars() has already
        // populated $_ENV['CORS_ALLOW_ORIGIN'] with the JSON-encoded list.
        $corsAllowOrigin = json_decode($_ENV['CORS_ALLOW_ORIGIN'] ?? '[]', true) ?: [];

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

        // The Swagger UI / ReDoc / GraphiQL HTML pages are rendered by Twig.
        // GraphiQL specifically hard-throws at container compile time when
        // enabled without TwigBundle, so this flag must reflect availability.
        // symfony/twig-bundle is a `suggest`-only dependency; check directly.
        $twigAvailable = class_exists(\Symfony\Bundle\TwigBundle\TwigBundle::class);

        $docsFormats = [
            'jsonld' => ['application/ld+json'],
            'json' => ['application/json'],
        ];
        if ($twigAvailable) {
            $docsFormats['html'] = ['text/html'];
        }

        $container->extension('api_platform', [
            'title' => 'Maho Commerce API',
            'version' => '2.0.0',
            'description' => 'Modern REST and GraphQL API for Maho Commerce',
            'enable_swagger_ui' => $twigAvailable,
            'enable_re_doc' => $twigAvailable,
            'enable_entrypoint' => true,
            'enable_docs' => true,
            'formats' => [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
            ],
            'docs_formats' => $docsFormats,
            'defaults' => [
                'route_prefix' => '/rest/v2',
                'pagination_enabled' => true,
                'pagination_items_per_page' => 20,
                'pagination_maximum_items_per_page' => 100,
                'cache_headers' => [
                    'vary' => ['Accept', 'Authorization', 'X-Store-Code'],
                ],
            ],
            'graphql' => [
                'enabled' => true,
                'graphiql' => ['enabled' => $twigAvailable && $this->isDebug()],
                // Introspection lets any unauthenticated client enumerate the
                // full schema, we only enable it in debug so dev tools work,
                // but keep the production attack surface minimal.
                'introspection' => ['enabled' => $this->isDebug()],
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
            // 'mapping' is injected dynamically in build() from ModuleApiDiscovery.
            // The static path that used to live here (%kernel.project_dir%/ApiResource)
            // referenced the pre-refactor layout when all API resources lived in a single
            // symfony/ApiResource/ directory. After the move to per-module Mage/*/Api/
            // dirs, the static path is dead config and its target directory does not
            // exist, which causes Symfony ApiPlatformExtension::getResourcesToWatch()
            // to fatal at container compile time on any composer-starter install.
            'patch_formats' => [
                'json' => ['application/merge-patch+json'],
            ],
        ]);

        // allow_credentials is pinned to false in both blocks: combined with a
        // reflected origin (which NelmioCors does when `*` is present, even
        // though we filter it out at env-resolution time) it produces a
        // cross-site credentialed-request vector. The API is bearer-token
        // authenticated, no cookies/credentials need to flow cross-origin.
        $container->extension('nelmio_cors', [
            'defaults' => [
                'origin_regex' => false,
                'allow_origin' => $corsAllowOrigin,
                'allow_credentials' => false,
                'allow_methods' => ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'allow_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'expose_headers' => ['Link', 'Deprecation', 'Sunset'],
                'max_age' => 3600,
            ],
            'paths' => [
                '^/api/' => [
                    'allow_origin' => $corsAllowOrigin,
                    'allow_credentials' => false,
                    'allow_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Store-Code'],
                    'allow_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'max_age' => 3600,
                ],
            ],
        ]);

        $container->extension('security', [
            // ROLE_ADMIN inherits ROLE_API_USER so admin tokens can reach the
            // REST endpoints gated by ROLE_API_USER (products, categories,
            // CMS, etc.), the listener AdminAclListener (default-deny via
            // ADMIN_RESOURCE) is the actual gate, not the security
            // expression. ROLE_USER is NOT inherited because customer-only
            // endpoints (`/me`, `/me/orders`, etc.) shouldn't be reached by
            // admin tokens at all; AdminAclListener default-denies those
            // since they don't declare ADMIN_RESOURCE.
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_API_USER'],
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

        // Translate IriConverter input errors (bare `id: 1` instead of an IRI)
        // into proper GraphQL null-results / 404s instead of HTTP 500. See the
        // class docblock for the full rationale.
        $services->set(GraphQl\IriToleranceProvider::class)
            ->decorate('api_platform.graphql.state_provider.read')
            ->arg('$inner', new Reference(GraphQl\IriToleranceProvider::class . '.inner'));

        $services->set(EventListener\DefaultDenyListener::class)
            ->arg('$resourceMetadataFactory', new Reference('api_platform.metadata.resource.metadata_collection_factory'))
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 28]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'api_platform')->prefix('/api');
        $routes->import($this->getProjectDir() . '/Controller/', 'attribute');
    }

    /**
     * Boot the kernel, register module autoloader on every request.
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
                    if ($this->isDebug()) {
                        // In debug, surface module load failures instead of
                        // silently dropping them, a broken Api class would
                        // otherwise yield "no resources found" with no clue.
                        throw $e;
                    }
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
     * This allows zero-config deployment, no composer dumpautoload needed.
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
