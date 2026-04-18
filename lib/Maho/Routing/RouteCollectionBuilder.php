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

    /**
     * Resolve route metadata for URL generation from frontName/controller/action.
     *
     * @return array{name: string, path: string, pathVariables: string[], area: string}|null
     */
    public static function resolveRoute(string $frontName, string $controllerName, string $actionName): ?array
    {
        $compiled = \Maho::getCompiledAttributes();
        $lookupKey = self::normalizeFrontName($frontName) . '/' . $controllerName . '/' . strtolower($actionName);
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
     * @return string|null The module class prefix (e.g. 'Mage_Customer') or null if not found
     */
    public static function resolveControllerModule(string $frontName, string $controllerName): ?string
    {
        $compiled = \Maho::getCompiledAttributes();
        $key = self::normalizeFrontName($frontName) . '/' . $controllerName;
        return $compiled['controllerLookup'][$key] ?? null;
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
        if ($routeName === 'adminhtml') {
            return self::getAdminFrontName();
        }
        return null;
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
     * Kept for backward compatibility with the pre-dumper static API.
     */
    public static function getAdminFrontNameStatic(): string
    {
        return self::getAdminFrontName();
    }

    /**
     * Instantiate the compiled URL matcher. The compiled data is loaded once
     * per process and shared across requests via opcache.
     */
    public static function createMatcher(RequestContext $context): CompiledUrlMatcher
    {
        if (self::$compiledMatcher === null) {
            $file = \Maho::getBasePath() . '/vendor/composer/maho_url_matcher.php';
            self::$compiledMatcher = file_exists($file) ? (require $file) : [false, [], [], [], null];
        }
        return new CompiledUrlMatcher(self::$compiledMatcher, $context);
    }

    /**
     * Instantiate the compiled URL generator.
     */
    public static function createGenerator(RequestContext $context): CompiledUrlGenerator
    {
        if (self::$compiledGenerator === null) {
            $file = \Maho::getBasePath() . '/vendor/composer/maho_url_generator.php';
            self::$compiledGenerator = file_exists($file) ? (require $file) : [];
        }
        return new CompiledUrlGenerator(self::$compiledGenerator, $context);
    }
}
