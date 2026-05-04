<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Routing;

use Mage;
use Mage_Adminhtml_Helper_Data;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * Accessor for routing data compiled at `composer dump-autoload`.
 *
 * Four compiled artifacts live under `vendor/composer/`:
 *   - `maho_attributes.php`      — raw attribute data + reverse lookups
 *   - `maho_url_matcher.php`     — CompiledUrlMatcher data (opcached)
 *   - `maho_url_generator.php`   — CompiledUrlGenerator data (opcached)
 *
 * Admin routes are compiled with `{_adminFrontName}` as a placeholder because the runtime
 * admin frontName is configurable via `use_custom_admin_path`. Reverse-lookup maps key admin
 * routes by the sentinel `__admin__`; the runtime translates the incoming frontName to this
 * sentinel. See `AttributeCompiler::ADMIN_SENTINEL`.
 */
class RouteCollectionBuilder
{
    public const ADMIN_SENTINEL = '__admin__';
    public const INSTALL_SENTINEL = '__install__';

    private static ?string $adminFrontName = null;

    /** @var array<string, mixed>|null */
    private static ?array $compiledMatcher = null;

    /** @var array<string, mixed>|null */
    private static ?array $compiledGenerator = null;

    /** @var array<string, string>|null frontName (lowercased) → module class prefix */
    private static ?array $legacyFrontNames = null;

    /**
     * Resolve route metadata for URL generation from frontName/controller/action.
     *
     * @return array{name: string, path: string, pathVariables: string[], area: string}|null
     */
    public static function resolveRoute(string $frontName, string $controllerName, string $actionName): ?array
    {
        $compiled = \Maho::getCompiledAttributes();
        $lookupKey = self::normalizeFrontName($frontName) . '/' . strtolower($controllerName) . '/' . strtolower($actionName);
        $routeName = $compiled['reverseLookup'][$lookupKey] ?? null;
        if ($routeName === null) {
            return null;
        }

        $route = $compiled['routes'][$routeName] ?? null;
        if ($route === null) {
            return null;
        }

        return [
            'name' => $routeName,
            'path' => $route['path'],
            'pathVariables' => $route['pathVariables'],
            'area' => $route['area'],
        ];
    }

    /**
     * Resolve a controller class from frontName + controllerName.
     *
     * Legacy `<frontend><routers>` XML declarations (BC shim) take precedence
     * over the compiled attribute lookup, so a legacy module shadowing a core
     * frontName (M1 semantics) still wins. If the legacy module can't satisfy
     * the request (no such controller class), the dispatcher falls through to
     * the Symfony matcher via the normal miss path.
     *
     * @return string|null The module class prefix (e.g. 'Mage_Customer') or null if not found
     */
    public static function resolveControllerModule(string $frontName, string $controllerName): ?string
    {
        $legacyModule = self::getLegacyFrontNames()[strtolower($frontName)] ?? null;
        if ($legacyModule !== null) {
            return $legacyModule;
        }

        $compiled = \Maho::getCompiledAttributes();
        $key = self::normalizeFrontName($frontName) . '/' . strtolower($controllerName);
        return $compiled['controllerLookup'][$key] ?? null;
    }

    /**
     * Legacy M1/OpenMage BC: discover frontName → module mappings declared in
     * `<frontend><routers><code><args><frontName>` config.xml blocks, for
     * modules that haven't been migrated to `#[Maho\Config\Route]` attributes.
     *
     * @return array<string, string> lowercase frontName → module class prefix
     */
    public static function getLegacyFrontNames(): array
    {
        if (self::$legacyFrontNames !== null) {
            return self::$legacyFrontNames;
        }

        $map = [];
        $pairs = [];
        $routersNode = Mage::getConfig()->getNode('frontend/routers');
        if ($routersNode) {
            foreach ($routersNode->children() as $router) {
                $frontName = trim((string) ($router->args->frontName ?? ''));
                $module = trim((string) ($router->args->module ?? ''));
                if ($frontName === '' || $module === '') {
                    continue;
                }
                $key = strtolower($frontName);
                if (isset($map[$key])) {
                    continue;
                }
                $map[$key] = $module;
                $pairs[] = $frontName . '→' . $module;
            }
        }

        if ($map !== []) {
            Mage::log(
                sprintf(
                    'Legacy XML routing active for %d frontName(s): %s. Add #[Maho\\Config\\Route] attributes to migrate.',
                    count($map),
                    implode(', ', $pairs),
                ),
                Mage::LOG_NOTICE,
            );
        }

        return self::$legacyFrontNames = $map;
    }

    /**
     * Translate a URL frontName to its reverse-lookup key (sentinel for admin/install, identity otherwise).
     */
    public static function normalizeFrontName(string $frontName): string
    {
        $lower = strtolower($frontName);
        if ($lower === strtolower(self::getAdminFrontName())) {
            return self::ADMIN_SENTINEL;
        }
        if ($lower === 'install') {
            return self::INSTALL_SENTINEL;
        }
        return $lower;
    }

    /**
     * Convert a route name to its URL frontName.
     * Only 'adminhtml' differs from its route name; all others are identity.
     */
    public static function getFrontNameByRoute(string $routeName): ?string
    {
        return match ($routeName) {
            'adminhtml' => self::getAdminFrontName(),
            'install' => 'install',
            default => null,
        };
    }

    /**
     * Convert a URL frontName to its route name.
     * Only the admin frontName maps to 'adminhtml'; all others are identity.
     */
    public static function getRouteByFrontName(string $frontName): ?string
    {
        if ($frontName === self::getAdminFrontName()) {
            return 'adminhtml';
        }
        return null;
    }

    public static function getAdminFrontName(): string
    {
        if (self::$adminFrontName !== null) {
            return self::$adminFrontName;
        }

        if ((string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH)) {
            $customUrl = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_CUSTOM_ADMIN_PATH);
            if ($customUrl !== '') {
                return self::$adminFrontName = $customUrl;
            }
        }

        return self::$adminFrontName = (string) Mage::getConfig()->getNode(
            Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME,
        );
    }

    /**
     * Instantiate the compiled URL matcher. The compiled data is loaded once
     * per process and shared across requests via opcache.
     *
     * @throws \RuntimeException if the compiled matcher file is missing — run `composer dump-autoload`.
     */
    public static function createMatcher(RequestContext $context): CompiledUrlMatcher
    {
        if (self::$compiledMatcher === null) {
            self::$compiledMatcher = self::loadCompiledFile('maho_url_matcher.php');
        }
        return new CompiledUrlMatcher(self::$compiledMatcher, $context);
    }

    /**
     * Instantiate the compiled URL generator.
     *
     * @throws \RuntimeException if the compiled generator file is missing — run `composer dump-autoload`.
     */
    public static function createGenerator(RequestContext $context): CompiledUrlGenerator
    {
        if (self::$compiledGenerator === null) {
            self::$compiledGenerator = self::loadCompiledFile('maho_url_generator.php');
        }
        return new CompiledUrlGenerator(self::$compiledGenerator, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadCompiledFile(string $filename): array
    {
        $file = \Maho::getBasePath() . '/vendor/composer/' . $filename;
        if (!file_exists($file)) {
            throw new \RuntimeException(sprintf(
                'Compiled routing file "%s" is missing. Run `composer dump-autoload` to regenerate it.',
                $file,
            ));
        }
        return require $file;
    }
}
