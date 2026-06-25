<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use DOMDocument;
use DOMElement;
use Maho\Config\Route;
use ReflectionClass;
use ReflectionMethod;
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
        $totalOverridesRemoved = 0;
        $totalOverridesSkipped = 0;

        // Pre-pass: detect sibling conflicts spanning two separate modules' config.xml. Each
        // file's per-<modules> check only sees overrides declared in the same node, so two
        // modules independently overriding the same base each look clean in isolation. We must
        // know about such cross-file conflicts BEFORE removing any XML, otherwise apply mode
        // would delete both protective <modules> nodes and only warn afterwards — too late, the
        // safety net is gone. Bases found in conflict here are treated as blockers below (left
        // untouched), keeping dry-run and apply behavior identical.
        /** @var array<string, list<string>> $globalByBase */
        $globalByBase = [];
        foreach ($this->findUserConfigXmlFiles() as $entry) {
            $dom = $this->loadConfigXmlAsDom($entry['path']);
            if ($dom === null) {
                continue;
            }
            foreach ($this->collectCleanOverridesByBase($dom) as $base => $classes) {
                foreach ($classes as $class) {
                    $globalByBase[$base][] = $class;
                }
            }
        }
        /** @var list<string> $conflictedBases */
        $conflictedBases = [];
        foreach ($globalByBase as $base => $classes) {
            if (count($classes) > 1 && $this->hasSiblingConflict($classes)) {
                $conflictedBases[] = $base;
            }
        }

        foreach ($this->findUserConfigXmlFiles() as $entry) {
            $module = $entry['module'];
            $configPath = $entry['path'];

            $dom = $this->loadConfigXmlAsDom($configPath);
            if ($dom === null) {
                $output->writeln(sprintf('<error>Could not parse %s</error>', $configPath));
                continue;
            }

            // Header is printed lazily so override-only modules (no <use> router) still show up.
            $headerPrinted = false;
            $ensureHeader = function () use (&$headerPrinted, $output, $module, $configPath): void {
                if (!$headerPrinted) {
                    $output->writeln(sprintf('<info>%s</info> (%s)', $module, $configPath));
                    $headerPrinted = true;
                }
            };
            $fileChanged = false;

            $routerEntries = $this->findRouterNodes($dom, $module);
            if ($routerEntries !== []) {
                $ensureHeader();
                $fileChanged = true;
            }

            // Override <modules> chains → drop the XML; inheritance now auto-registers them.
            // Run before the route loop: a router carrying BOTH a <use> route declaration and a
            // <modules> override chain must be classified (clean → removed, blocker → warned)
            // before detachAndPrune() below would otherwise drop the whole router silently.
            $this->migrateControllerOverrides(
                $dom,
                $output,
                $dryRun,
                $ensureHeader,
                $totalOverridesRemoved,
                $totalOverridesSkipped,
                $fileChanged,
                $conflictedBases,
            );

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

                // Bubble up through <routers> and the area scope if they become empty — but
                // never when a <modules> override chain survived classification (a blocker kept
                // it): pruning the whole router would silently drop that preserved override XML.
                $argsNode = $this->firstChildElement($routerNode, 'args');
                if ($argsNode === null || $this->firstChildElement($argsNode, 'modules') === null) {
                    $this->detachAndPrune($routerNode);
                }
            }

            if (!$dryRun && $fileChanged) {
                $this->saveConfigXml($dom, $configPath);
            }

            if ($headerPrinted) {
                $output->writeln('');
            }
        }

        $output->writeln(sprintf(
            '<info>Done.</info> Routers processed: %d, route attributes added: %d, skipped: %d. '
            . 'Override chains removed: %d, skipped: %d.',
            $totalRouters,
            $totalRoutes,
            $totalSkipped,
            $totalOverridesRemoved,
            $totalOverridesSkipped,
        ));

        $changed = $totalRoutes > 0 || $totalOverridesRemoved > 0;
        if ($dryRun && $changed) {
            $output->writeln('Re-run without --dry-run to apply.');
            $output->writeln('<comment>Tip: review the generated routes carefully; controller-method scanning is best-effort.</comment>');
        } elseif (!$dryRun && $changed) {
            $output->writeln('Run <info>composer dump-autoload</info> to compile the new attributes.');
            $output->writeln('<comment>Tip: review the generated routes carefully; controller-method scanning is best-effort.</comment>');
        }

        $this->migrateLocalXmlAdminPath($output, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * Yield every `<{scope}><routers><{code}><args><modules>` node in a config.xml, paired with a
     * human-readable `{scope}/{code}` label. Shared by the cross-file pre-pass (read-only) and the
     * migration pass (mutating) so both walk the tree identically. Router children are snapshotted,
     * so callers may detach nodes while iterating.
     *
     * @return iterable<array{node: DOMElement, label: string}>
     */
    private function iterateOverrideModulesNodes(DOMDocument $dom): iterable
    {
        $config = $dom->documentElement;
        if ($config === null) {
            return;
        }

        foreach (['frontend', 'admin', 'install'] as $scope) {
            $scopeNode = $this->firstChildElement($config, $scope);
            if ($scopeNode === null) {
                continue;
            }
            $routersNode = $this->firstChildElement($scopeNode, 'routers');
            if ($routersNode === null) {
                continue;
            }
            foreach (iterator_to_array($routersNode->childNodes) as $routerNode) {
                if (!$routerNode instanceof DOMElement) {
                    continue;
                }
                $argsNode = $this->firstChildElement($routerNode, 'args');
                if ($argsNode === null) {
                    continue;
                }
                $modulesNode = $this->firstChildElement($argsNode, 'modules');
                if ($modulesNode === null) {
                    continue;
                }
                yield ['node' => $modulesNode, 'label' => $scope . '/' . $routerNode->localName];
            }
        }
    }

    /**
     * The non-empty module prefixes declared inside a `<modules>` override node, in document order.
     *
     * @return list<string>
     */
    private function overrideModulePrefixes(DOMElement $modulesNode): array
    {
        $prefixes = [];
        foreach ($modulesNode->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $prefix = trim($child->textContent);
                if ($prefix !== '') {
                    $prefixes[] = $prefix;
                }
            }
        }
        return $prefixes;
    }

    /**
     * Collect, grouped by routed base class, every clean override declared in a config.xml's
     * `<args><modules>` chains. Read-only (no output, no DOM mutation) — used by the pre-pass to
     * detect sibling conflicts that span two separate files before any XML is removed. Only
     * overrides that would actually be removed (pure subclasses of a routed base that add no
     * un-routed actions) are reported; anything that is itself a per-file blocker is irrelevant
     * to cross-file conflict detection since its node is never removed.
     *
     * @return array<string, list<string>>
     */
    private function collectCleanOverridesByBase(DOMDocument $dom): array
    {
        $byBase = [];
        foreach ($this->iterateOverrideModulesNodes($dom) as $entry) {
            foreach ($this->overrideModulePrefixes($entry['node']) as $prefix) {
                foreach ($this->findControllers($prefix) as $controller) {
                    $analysis = $this->analyzeOverrideController($controller['className']);
                    if ($analysis['pure'] && $analysis['newActions'] === []) {
                        $byBase[(string) $analysis['base']][] = $controller['className'];
                    }
                }
            }
        }

        return $byBase;
    }

    /**
     * Migrate legacy controller-override chains to the attribute era.
     *
     * `<{scope}><routers><{code}><args><modules><My_Module .../></modules>` piggybacks a
     * module onto an existing frontName purely to override its controllers. Because override
     * controllers already extend the core controller, Maho now auto-registers them as
     * overrides at `composer dump-autoload`, so migrating just means deleting the XML.
     *
     * A `<modules>` node is removed only when every declared override is a clean
     * re-implementation of inherited, routed actions. Anything needing a human — a controller
     * that isn't a subclass of a routed controller, one adding un-routed actions, or sibling
     * modules overriding the same controller with no shared inheritance chain (whether in this
     * file or across files, via $conflictedBases) — is reported and the XML left untouched, so
     * re-running after a manual fix is safe.
     *
     * @param list<string> $conflictedBases routed base classes with a cross-file sibling conflict
     */
    private function migrateControllerOverrides(
        DOMDocument $dom,
        OutputInterface $output,
        bool $dryRun,
        callable $ensureHeader,
        int &$removed,
        int &$skipped,
        bool &$fileChanged,
        array $conflictedBases,
    ): void {
        foreach ($this->iterateOverrideModulesNodes($dom) as $entry) {
            $this->migrateOverrideModulesNode(
                $entry['node'],
                $entry['label'],
                $output,
                $dryRun,
                $ensureHeader,
                $removed,
                $skipped,
                $fileChanged,
                $conflictedBases,
            );
        }
    }

    /**
     * Analyze and (when safe) remove a single `<args><modules>` override chain.
     *
     * @param list<string> $conflictedBases routed base classes with a cross-file sibling conflict
     */
    private function migrateOverrideModulesNode(
        DOMElement $modulesNode,
        string $routerLabel,
        OutputInterface $output,
        bool $dryRun,
        callable $ensureHeader,
        int &$removed,
        int &$skipped,
        bool &$fileChanged,
        array $conflictedBases,
    ): void {
        $modulePrefixes = $this->overrideModulePrefixes($modulesNode);
        if ($modulePrefixes === []) {
            // Empty <modules> wrapper carries nothing — drop the dead XML.
            $ensureHeader();
            $removed++;
            if ($dryRun) {
                $output->writeln(sprintf('  would remove empty %s override chain', $routerLabel));
            } else {
                $this->detachAndPrune($modulesNode);
                $fileChanged = true;
                $output->writeln(sprintf('  <info>migrated</info> removed empty %s override chain', $routerLabel));
            }
            return;
        }

        $ensureHeader();

        /** @var list<string> $blockers */
        $blockers = [];
        $overrideClasses = [];
        /** @var array<string, list<string>> $byBase */
        $byBase = [];

        foreach ($modulePrefixes as $prefix) {
            $controllers = $this->findControllers($prefix);
            if ($controllers === []) {
                $blockers[] = sprintf('module %s declares no controllers', $prefix);
                continue;
            }
            foreach ($controllers as $controller) {
                $class = $controller['className'];
                $analysis = $this->analyzeOverrideController($class);
                if (!$analysis['pure']) {
                    $blockers[] = sprintf('%s %s', $class, $analysis['reason']);
                    continue;
                }
                if ($analysis['newActions'] !== []) {
                    $actions = implode(', ', array_map(fn(string $a): string => $a . 'Action', $analysis['newActions']));
                    $blockers[] = sprintf(
                        '%s adds un-routed action(s) [%s] — add #[Maho\\Config\\Route] for them first',
                        $class,
                        $actions,
                    );
                    continue;
                }
                $overrideClasses[] = $class;
                $byBase[(string) $analysis['base']][] = $class;
            }
        }

        // Sibling conflict: two overrides of the same base with no shared inheritance chain.
        // Check both within this node ($byBase) and across files ($conflictedBases, computed in
        // the pre-pass) so the conflicting XML is left in place rather than silently removed.
        foreach ($byBase as $base => $classes) {
            if (count($classes) > 1 && $this->hasSiblingConflict($classes)) {
                $blockers[] = sprintf(
                    'overrides of %s have no shared inheritance chain (%s) — make one extend the other',
                    $base,
                    implode(', ', $classes),
                );
            } elseif (in_array($base, $conflictedBases, true)) {
                $blockers[] = sprintf(
                    'overrides of %s across modules have no shared inheritance chain — make one '
                    . 'extend the other before running composer dump-autoload',
                    $base,
                );
            }
        }

        if ($blockers !== []) {
            $output->writeln(sprintf('  <comment>skip</comment> %s override chain:', $routerLabel));
            foreach ($blockers as $reason) {
                $output->writeln(sprintf('    - %s', $reason));
            }
            $skipped++;
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf(
                '  would remove %s override chain (%d override(s) now auto-registered by inheritance)',
                $routerLabel,
                count($overrideClasses),
            ));
            $removed++;
            return;
        }

        $this->detachAndPrune($modulesNode);
        $fileChanged = true;
        $removed++;
        $output->writeln(sprintf(
            '  <info>migrated</info> removed %s override chain (%d override(s) auto-registered by inheritance)',
            $routerLabel,
            count($overrideClasses),
        ));
    }

    /**
     * Classify an override controller: is it a clean subclass of a routed controller that
     * only re-implements inherited actions (safe to drop from XML), and which routed base
     * does it extend?
     *
     * @return array{pure: bool, base: ?string, reason: string, newActions: list<string>}
     */
    private function analyzeOverrideController(string $className): array
    {
        $fail = fn(string $reason): array => ['pure' => false, 'base' => null, 'reason' => $reason, 'newActions' => []];

        if ($this->findClassFile($className) === null) {
            return $fail('is not autoloadable');
        }

        $reflection = new ReflectionClass($className);
        $parent = $reflection->getParentClass();
        if ($parent === false) {
            return $fail('extends no controller (needs its own #[Maho\\Config\\Route])');
        }

        $base = null;
        for ($ancestor = $parent; $ancestor !== false; $ancestor = $ancestor->getParentClass()) {
            if ($this->controllerOwnsRoutes($ancestor)) {
                $base = $ancestor->getName();
                break;
            }
        }
        if ($base === null) {
            return $fail('extends no routed controller (needs its own #[Maho\\Config\\Route])');
        }

        // Public *Action methods the subclass declares that the routed base doesn't have are
        // brand-new, un-routed actions — inheritance only carries routes from $base, so an
        // action absent there (even if an intermediate override added it) can't be auto-routed.
        $newActions = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                continue;
            }
            $name = $method->getName();
            if (str_ends_with($name, 'Action') && !method_exists($base, $name)) {
                $newActions[] = substr($name, 0, -strlen('Action'));
            }
        }

        return ['pure' => true, 'base' => $base, 'reason' => '', 'newActions' => $newActions];
    }

    /**
     * Whether the class declares at least one `#[Maho\Config\Route]` of its own.
     *
     * @param ReflectionClass<object> $class
     */
    private function controllerOwnsRoutes(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            if ($method->getAttributes(Route::class) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when the override classes don't reduce to a single most-derived class — i.e. two or
     * more are mutually incomparable (siblings), mirroring the compiler's conflict detection.
     *
     * @param list<string> $classes
     */
    private function hasSiblingConflict(array $classes): bool
    {
        // Dedup first: the same class discovered twice (e.g. a module present in two code dirs)
        // would otherwise each count as "maximal" and fake a conflict.
        $classes = array_values(array_unique($classes));
        $maximal = 0;
        foreach ($classes as $candidate) {
            $isMaximal = array_all($classes, fn($other) => !($other !== $candidate && is_subclass_of($other, $candidate)));
            if ($isMaximal) {
                $maximal++;
            }
        }
        return $maximal > 1;
    }

    /**
     * Rewrite the legacy admin frontName declaration in `app/etc/local.xml`.
     *
     * Old (no longer honored after PR #834):
     *   <admin><routers><adminhtml><args><frontName>X</frontName>
     * New:
     *   <admin><base_path>X</base_path>
     */
    private function migrateLocalXmlAdminPath(OutputInterface $output, bool $dryRun): void
    {
        $path = MAHO_ROOT_DIR . '/app/etc/local.xml';
        if (!is_file($path)) {
            return;
        }

        $dom = $this->loadConfigXmlAsDom($path);
        if ($dom === null) {
            return;
        }

        $config = $dom->documentElement;
        if ($config === null) {
            return;
        }

        $adminNode = $this->firstChildElement($config, 'admin');
        if ($adminNode === null) {
            return;
        }
        $routersNode = $this->firstChildElement($adminNode, 'routers');
        if ($routersNode === null) {
            return;
        }
        $adminhtmlNode = $this->firstChildElement($routersNode, 'adminhtml');
        if ($adminhtmlNode === null) {
            return;
        }
        $argsNode = $this->firstChildElement($adminhtmlNode, 'args');
        if ($argsNode === null) {
            return;
        }
        $frontNameNode = $this->firstChildElement($argsNode, 'frontName');
        if ($frontNameNode === null) {
            return;
        }
        $frontName = trim($frontNameNode->textContent);
        if ($frontName === '') {
            return;
        }

        $existingBasePath = $this->firstChildElement($adminNode, 'base_path');
        $existingBasePathValue = $existingBasePath === null ? null : trim($existingBasePath->textContent);

        $output->writeln('');
        $output->writeln('<info>app/etc/local.xml</info> (admin frontName)');

        if ($existingBasePathValue !== null && $existingBasePathValue !== '' && $existingBasePathValue !== $frontName) {
            $output->writeln(sprintf(
                '  <comment>skip</comment> <admin><base_path>%s</base_path> already differs from legacy <frontName>%s</frontName>; resolve manually.',
                $existingBasePathValue,
                $frontName,
            ));
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf(
                '  would replace <admin><routers><adminhtml><args><frontName>%s</frontName>... with <admin><base_path>%s</base_path>',
                $frontName,
                $frontName,
            ));
            return;
        }

        if ($existingBasePath === null) {
            $newNode = $dom->createElement('base_path', $frontName);
            $adminNode->insertBefore($newNode, $routersNode);
        }

        $this->detachAndPrune($adminhtmlNode);
        $this->saveConfigXml($dom, $path);

        $output->writeln(sprintf(
            '  <info>migrated</info> <admin><base_path>%s</base_path>',
            $frontName,
        ));
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
