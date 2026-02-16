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

namespace Maho\ApiPlatform\DependencyInjection;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Discovery\ModuleApiDiscovery;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Registers module API services (providers, processors, resources) and
 * auto-tags providers with 'maho.api.state_provider' for CustomQueryResolver.
 */
final class ModuleApiCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $discovery = ModuleApiDiscovery::discover();

        foreach ($discovery['namespaces'] as $namespace => $baseDir) {
            $this->registerServices($container, $namespace, $baseDir);
        }
    }

    private function registerServices(ContainerBuilder $container, string $namespace, string $baseDir): void
    {
        if (!is_dir($baseDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Build FQCN from file path
            $relativePath = substr($file->getPathname(), strlen($baseDir) + 1);
            $class = $namespace . str_replace('/', '\\', substr($relativePath, 0, -4));

            if (!class_exists($class)) {
                continue;
            }

            // Skip if already registered (e.g. by services.yaml)
            if ($container->hasDefinition($class)) {
                continue;
            }

            $definition = new Definition($class);
            $definition->setAutowired(true);
            $definition->setAutoconfigured(true);
            $definition->setPublic(false);

            $ref = new \ReflectionClass($class);

            // Providers and processors must be public so API Platform's
            // CallableProvider/CallableProcessor can resolve them at runtime
            if ($ref->implementsInterface(ProviderInterface::class)) {
                $definition->addTag('maho.api.state_provider', ['key' => $class]);
                $definition->setPublic(true);
            }
            if ($ref->implementsInterface(ProcessorInterface::class)) {
                $definition->setPublic(true);
            }

            $container->setDefinition($class, $definition);
        }
    }
}
