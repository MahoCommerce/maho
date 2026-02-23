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

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Discovery\ModuleApiDiscovery;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

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
        return dirname(__DIR__);
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

        // Phase 2: Load all files (Resource DTOs first, then others)
        // Sort so Resource/ files load before State/ files to resolve dependencies
        uksort($files, function ($a, $b) {
            $aIsResource = str_contains($a, '\\Resource\\');
            $bIsResource = str_contains($b, '\\Resource\\');
            if ($aIsResource && !$bIsResource) {
                return -1;
            }
            if (!$aIsResource && $bIsResource) {
                return 1;
            }
            return strcmp($a, $b);
        });

        foreach ($files as $class => $filePath) {
            if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
                try {
                    require_once $filePath;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        // Phase 3: Register all loaded classes as services
        foreach ($files as $class => $filePath) {
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
