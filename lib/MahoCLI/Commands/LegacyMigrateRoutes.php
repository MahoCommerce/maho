<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use DOMElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy:migrate-routes',
    description: 'Migrate XML <routers> declarations in user modules to #[Maho\\Config\\Route] attributes',
)]
class LegacyMigrateRoutes extends BaseMahoCommand
{
    use LegacyMigrateTrait;

    private const SCOPE_TO_AREA = [
        'frontend' => ['use' => 'standard', 'area' => 'frontend', 'pathPrefix' => ''],
        'admin' => ['use' => 'admin', 'area' => 'adminhtml', 'pathPrefix' => '/admin'],
        'install' => ['use' => 'install', 'area' => 'install', 'pathPrefix' => ''],
    ];

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview the changes without writing any files',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $output->writeln('<comment>Dry run: no files will be modified.</comment>');
            $output->writeln('');
        }

        $totalRoutes = 0;
        $totalSkipped = 0;
        $totalRouters = 0;

        foreach ($this->findUserConfigXmlFiles() as $entry) {
            $module = $entry['module'];
            $configPath = $entry['path'];

            $dom = $this->loadConfigXmlAsDom($configPath);
            if ($dom === null) {
                $output->writeln(sprintf('<error>Could not parse %s</error>', $configPath));
                continue;
            }

            $routerEntries = $this->findRouterNodes($dom, $module);
            if ($routerEntries === []) {
                continue;
            }

            $output->writeln(sprintf('<info>%s</info> (%s)', $module, $configPath));

            foreach ($routerEntries as $routerEntry) {
                $totalRouters++;
                $routerNode = $routerEntry['node'];
                $frontName = $routerEntry['frontName'];
                $moduleName = $routerEntry['moduleName'];
                $pathPrefix = $routerEntry['pathPrefix'];

                $controllers = $this->findControllers($moduleName);
                if ($controllers === []) {
                    $output->writeln(sprintf(
                        '  <comment>skip</comment> router "%s": no controllers found for module %s',
                        $frontName,
                        $moduleName,
                    ));
                    $totalSkipped++;
                    continue;
                }

                foreach ($controllers as $controller) {
                    $controllerFile = $controller['file'];
                    $controllerPath = $controller['urlSegment'];
                    $actions = $this->findActionMethods($controllerFile);

                    if ($actions === []) {
                        continue;
                    }

                    foreach ($actions as $action) {
                        $routes = $this->buildRoutes($pathPrefix, $frontName, $controllerPath, $action);
                        $attributeLines = array_map(
                            fn(array $r) => $this->formatAttributeLine($r['path'], $r['name']),
                            $routes,
                        );

                        if ($dryRun) {
                            foreach ($attributeLines as $line) {
                                $output->writeln(sprintf('  would add %s', $line));
                            }
                            $output->writeln(sprintf(
                                '    on %s::%sAction in %s',
                                $controller['className'],
                                $action,
                                $this->relativePath($controllerFile),
                            ));
                        } else {
                            $allOk = true;
                            // Insert in reverse order so the most-specific route ends up at the top
                            foreach (array_reverse($attributeLines) as $line) {
                                $ok = $this->insertMethodAttribute($controllerFile, $action . 'Action', $line);
                                if (!$ok) {
                                    $allOk = false;
                                    break;
                                }
                            }
                            if (!$allOk) {
                                $output->writeln(sprintf(
                                    '  <error>fail</error> %s::%sAction not found in %s',
                                    $controller['className'],
                                    $action,
                                    $this->relativePath($controllerFile),
                                ));
                                $totalSkipped++;
                                continue;
                            }
                            $output->writeln(sprintf(
                                '  <info>migrated</info> %s::%sAction (%d route(s))',
                                $controller['className'],
                                $action,
                                count($routes),
                            ));
                        }
                        $totalRoutes += count($routes);
                    }
                }

                // Bubble up through <routers> and the area scope if they become empty.
                $this->detachAndPrune($routerNode);
            }

            if (!$dryRun) {
                $this->saveConfigXml($dom, $configPath);
            }

            $output->writeln('');
        }

        $output->writeln(sprintf(
            '<info>Done.</info> Routers processed: %d, route attributes added: %d, skipped: %d.',
            $totalRouters,
            $totalRoutes,
            $totalSkipped,
        ));

        if ($dryRun && $totalRoutes > 0) {
            $output->writeln('Re-run without --dry-run to apply.');
            $output->writeln('<comment>Tip: review the generated routes carefully; controller-method scanning is best-effort.</comment>');
        } elseif (!$dryRun && $totalRoutes > 0) {
            $output->writeln('Run <info>composer dump-autoload</info> to compile the new attributes.');
            $output->writeln('<comment>Tip: review the generated routes carefully; controller-method scanning is best-effort.</comment>');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{node: DOMElement, frontName: string, moduleName: string, area: string, pathPrefix: string}>
     */
    private function findRouterNodes(\DOMDocument $dom, string $defaultModule): array
    {
        $found = [];
        $config = $dom->documentElement;
        if ($config === null) {
            return $found;
        }

        foreach (self::SCOPE_TO_AREA as $scope => $info) {
            $scopeNode = $this->firstChildElement($config, $scope);
            if ($scopeNode === null) {
                continue;
            }
            $routersNode = $this->firstChildElement($scopeNode, 'routers');
            if ($routersNode === null) {
                continue;
            }
            foreach ($routersNode->childNodes as $routerNode) {
                if (!$routerNode instanceof DOMElement) {
                    continue;
                }
                $useNode = $this->firstChildElement($routerNode, 'use');
                if ($useNode === null || trim($useNode->textContent) !== $info['use']) {
                    continue;
                }
                $argsNode = $this->firstChildElement($routerNode, 'args');
                $moduleName = $defaultModule;
                $frontName = $routerNode->localName;
                if ($argsNode !== null) {
                    $moduleNode = $this->firstChildElement($argsNode, 'module');
                    if ($moduleNode !== null && trim($moduleNode->textContent) !== '') {
                        $moduleName = trim($moduleNode->textContent);
                    }
                    $frontNameNode = $this->firstChildElement($argsNode, 'frontName');
                    if ($frontNameNode !== null && trim($frontNameNode->textContent) !== '') {
                        $frontName = trim($frontNameNode->textContent);
                    }
                }
                $found[] = [
                    'node' => $routerNode,
                    'frontName' => $frontName,
                    'moduleName' => $moduleName,
                    'area' => $info['area'],
                    'pathPrefix' => $info['pathPrefix'],
                ];
            }
        }
        return $found;
    }

    private function firstChildElement(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }
        return null;
    }

    /**
     * @return list<array{file: string, className: string, urlSegment: string}>
     */
    private function findControllers(string $moduleName): array
    {
        $parts = explode('_', $moduleName, 2);
        if (count($parts) !== 2) {
            return [];
        }
        [$vendor, $module] = $parts;

        $candidates = [];
        foreach ($this->userCodeDirs as $codeDir) {
            $base = MAHO_ROOT_DIR . '/' . $codeDir . '/' . $vendor . '/' . $module . '/controllers';
            if (is_dir($base)) {
                $candidates[] = $base;
            }
        }
        if ($candidates === []) {
            return [];
        }

        $found = [];
        foreach ($candidates as $base) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (!str_ends_with($file->getFilename(), 'Controller.php')) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($base) + 1);
                // e.g. "IndexController.php" or "Sub/FooController.php"
                $withoutSuffix = substr($relative, 0, -strlen('Controller.php'));
                $segments = explode('/', $withoutSuffix);
                $urlSegment = strtolower(implode('_', $segments));
                $classSuffix = str_replace('/', '_', $withoutSuffix) . 'Controller';
                $className = $moduleName . '_' . $classSuffix;

                $found[] = [
                    'file' => $file->getPathname(),
                    'className' => $className,
                    'urlSegment' => $urlSegment,
                ];
            }
        }
        return $found;
    }

    /**
     * @return list<string> Action method short names (without 'Action' suffix)
     */
    private function findActionMethods(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return [];
        }
        if (!preg_match_all(
            '/^\s*(?:public|protected|private)?\s*(?:static\s+)?function\s+(\w+)Action\s*\(/m',
            $contents,
            $matches,
        )) {
            return [];
        }
        return array_values(array_unique($matches[1]));
    }

    /**
     * Builds the M1-equivalent route paths for a single action.
     *
     * For {controller}/{action} = "index/index" we emit three paths to preserve
     * legacy URL collapsing: /front, /front/index, /front/index/index.
     * For "{any}/index" we emit two: /front/{any}, /front/{any}/index.
     * For "{any}/{any}" we emit one.
     *
     * @return list<array{path: string, name: string}>
     */
    private function buildRoutes(string $pathPrefix, string $frontName, string $controllerPath, string $action): array
    {
        $base = rtrim($pathPrefix, '/') . '/' . $frontName;
        $routes = [];
        $isIndexController = $controllerPath === 'index';
        $isIndexAction = $action === 'index';

        if ($isIndexController && $isIndexAction) {
            $routes[] = ['path' => $base, 'name' => $frontName];
            $routes[] = ['path' => $base . '/index', 'name' => $frontName . '.index'];
            $routes[] = ['path' => $base . '/index/index', 'name' => $frontName . '.index.index'];
        } elseif ($isIndexAction) {
            $routes[] = ['path' => $base . '/' . $controllerPath, 'name' => $frontName . '.' . $controllerPath];
            $routes[] = ['path' => $base . '/' . $controllerPath . '/index', 'name' => $frontName . '.' . $controllerPath . '.index'];
        } else {
            $routes[] = [
                'path' => $base . '/' . $controllerPath . '/' . $action,
                'name' => $frontName . '.' . $controllerPath . '.' . $action,
            ];
        }
        return $routes;
    }

    private function formatAttributeLine(string $path, string $name): string
    {
        return sprintf(
            '#[\\Maho\\Config\\Route(%s, name: %s)]',
            var_export($path, true),
            var_export($name, true),
        );
    }

    private function relativePath(string $absolute): string
    {
        $root = MAHO_ROOT_DIR . '/';
        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }
        return $absolute;
    }
}
