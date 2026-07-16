<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use ApiPlatform\Metadata\HttpOperation;
use Mage;
use Maho\ApiPlatform\Discovery\ModuleApiDiscovery;
use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Maho\Config\ApiResource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:api:resource:list',
    description: 'List the API Platform resources discovered across all modules (REST routes, methods, permissions)',
)]
class ListApiResources extends BaseMahoCommand
{
    use ApiResourceNaming;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Only show resources from this module (e.g., Maho_Blog)')
            ->addOption('section', 's', InputOption::VALUE_REQUIRED, 'Only show resources in this permission section (e.g., Catalog)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $io = new SymfonyStyle($input, $output);

        $resources = $this->collectResources();

        // Apply filters
        $moduleFilter = $input->getOption('module');
        $sectionFilter = $input->getOption('section');
        if ($moduleFilter) {
            $resources = array_filter($resources, fn($r) => strcasecmp($r['module'], $moduleFilter) === 0);
        }
        if ($sectionFilter) {
            $resources = array_filter($resources, fn($r) => strcasecmp($r['section'], $sectionFilter) === 0);
        }

        // Sort by module, then resource name
        usort($resources, fn($a, $b) => [$a['module'], $a['resource']] <=> [$b['module'], $b['resource']]);

        if ($input->getOption('json')) {
            $output->writeln(Mage::helper('core')->jsonEncode($resources));
            return Command::SUCCESS;
        }

        if ($resources === []) {
            $io->warning('No API resources found for the given filters.');
            return Command::SUCCESS;
        }

        $io->title('API Resources');
        $rows = [];
        foreach ($resources as $r) {
            $rows[] = [
                $r['module'],
                $r['resource'],
                $r['route'] ?: '-',
                implode(' ', $r['methods']) ?: '-',
                $r['graphql'] ? 'yes' : '-',
                $r['access'],
                $r['id'],
            ];
        }
        $io->table(
            ['Module', 'Resource', 'Route', 'Methods', 'GraphQL', 'Access', 'Permission ID'],
            $rows,
        );
        $io->text(sprintf('%d resource(s).', count($resources)));

        return Command::SUCCESS;
    }

    /**
     * Discover every Api/ DTO, reflect its #[ApiResource] attribute, and merge
     * in the compiled permission registry (section, labels, public/customer flags).
     *
     * @return list<array{module: string, resource: string, id: string, section: string, route: ?string, methods: list<string>, graphql: bool, access: string, provider: ?string}>
     */
    private function collectResources(): array
    {
        $discovery = ModuleApiDiscovery::discover();

        // Register a runtime autoloader for the discovered Api/ namespaces, exactly
        // as the API Platform kernel does on a web request (these classes are not
        // PSR-4 registered in the Composer autoloader).
        spl_autoload_register(function (string $class) use ($discovery): void {
            foreach ($discovery['namespaces'] as $ns => $dir) {
                if (str_starts_with($class, $ns)) {
                    $rel = str_replace('\\', '/', substr($class, strlen($ns)));
                    $file = "{$dir}/{$rel}.php";
                    if (is_file($file)) {
                        require $file;
                    }
                    return;
                }
            }
        });

        $registry = new ApiPermissionRegistry();
        $registryResources = $registry->getResources();

        $out = [];
        foreach ($discovery['namespaces'] as $ns => $dir) {
            foreach (glob("{$dir}/*.php") ?: [] as $file) {
                $class = $ns . basename($file, '.php');
                try {
                    if (!class_exists($class)) {
                        continue;
                    }
                    $ref = new \ReflectionClass($class);
                    $attrs = $ref->getAttributes(ApiResource::class, \ReflectionAttribute::IS_INSTANCEOF);
                    if ($attrs === []) {
                        continue; // Providers/Processors and non-resource classes
                    }
                    $out[] = $this->describe($ref, $attrs[0]->newInstance(), $registryResources, $registry);
                } catch (\Throwable) {
                    // Skip anything that fails to load/reflect rather than aborting the list
                    continue;
                }
            }
        }

        return $out;
    }

    /**
     * @param \ReflectionClass<object> $ref
     * @param array<string, array{label: string, section: string, operations: array<string, string>}> $registryResources
     * @return array{module: string, resource: string, id: string, section: string, route: ?string, methods: list<string>, graphql: bool, access: string, provider: ?string}
     */
    private function describe(\ReflectionClass $ref, object $attr, array $registryResources, ApiPermissionRegistry $registry): array
    {
        $class = $ref->getName();
        $shortName = (method_exists($attr, 'getShortName') ? $attr->getShortName() : null) ?: $ref->getShortName();
        $module = str_replace('\\', '_', explode('\\Api\\', $class)[0]);

        $explicitId = property_exists($attr, 'mahoId') ? $attr->mahoId : null;
        $customerScoped = property_exists($attr, 'mahoCustomerScoped') && $attr->mahoCustomerScoped;

        // Collect HTTP methods + a base route from the operations.
        $methods = [];
        $route = null;
        $operations = method_exists($attr, 'getOperations') ? $attr->getOperations() : null;
        if ($operations !== null) {
            foreach ($operations as $op) {
                if (!$op instanceof HttpOperation) {
                    continue;
                }
                $method = strtoupper((string) $op->getMethod());
                if ($method !== '' && !in_array($method, $methods, true)) {
                    $methods[] = $method;
                }
                $uri = $op->getUriTemplate();
                if ($uri !== null && ($route === null || !str_contains($uri, '{'))) {
                    // Prefer the collection route (no {id}); otherwise strip the id segment.
                    $route = str_contains($uri, '{') ? preg_replace('#/\{[^}]+\}$#', '', $uri) : $uri;
                }
            }
        }
        // Stable, familiar ordering.
        $order = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        usort($methods, fn($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        $graphql = method_exists($attr, 'getGraphQlOperations') && !empty($attr->getGraphQlOperations());

        // Permission id: explicit mahoId, else the compiler's exact derivation, so
        // the id we display/look up matches the registry key the compiler baked in.
        $lookupId = $explicitId ?? $this->deriveApiResourceId($shortName);

        // Customer-scoped resources are JWT-gated and register no admin grant, so
        // they don't appear in the registry's resource map, so classify accordingly.
        if ($customerScoped) {
            $access = 'customer';
            $displayId = $explicitId ?? '-';
        } elseif ($registry->isPublicRead($lookupId)) {
            $access = 'public read';
            $displayId = $lookupId;
        } else {
            $access = 'admin';
            $displayId = $lookupId;
        }

        $section = (property_exists($attr, 'mahoSection') ? $attr->mahoSection : null)
            ?? ($registryResources[$lookupId]['section'] ?? null)
            ?? (str_contains($module, '_') ? substr($module, (int) strpos($module, '_') + 1) : $module);

        $provider = method_exists($attr, 'getProvider') && is_string($attr->getProvider())
            ? $attr->getProvider()
            : null;

        return [
            'module' => $module,
            'resource' => $shortName,
            'id' => $displayId,
            'section' => $section,
            'route' => $route,
            'methods' => $methods,
            'graphql' => $graphql,
            'access' => $access,
            'provider' => $provider,
        ];
    }
}
