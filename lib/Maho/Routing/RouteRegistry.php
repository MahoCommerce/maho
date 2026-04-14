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

/**
 * Central registry for route metadata (frontName ↔ routeName ↔ module chain).
 *
 * Replaces the metadata functions previously provided by Router_Standard/Router_Admin.
 * Built from config.xml router declarations at init time. The module chain data
 * supports third-party controller overrides via before/after ordering.
 */
class RouteRegistry
{
    /**
     * frontName → [moduleName, ...] (module chain with before/after ordering)
     * @var array<string, string[]>
     */
    protected static array $modules = [];

    /**
     * routeName → frontName
     * @var array<string, string>
     */
    protected static array $routes = [];

    protected static bool $initialized = false;

    /**
     * Build the registry from config.xml router declarations.
     *
     * Called once during front controller init. Replicates the logic from
     * Router_Standard::collectRoutes() and Router_Admin::collectRoutes().
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$modules = [];
        self::$routes = [];

        self::collectArea('frontend', 'standard');
        self::collectArea('admin', 'admin');

        self::$initialized = true;
    }

    public static function reset(): void
    {
        self::$modules = [];
        self::$routes = [];
        self::$initialized = false;
    }

    protected static function collectArea(string $configArea, string $useRouterName): void
    {
        $routersConfigNode = Mage::getConfig()->getNode($configArea . '/routers');
        if (!$routersConfigNode) {
            return;
        }

        // Admin router: apply custom admin URL before collecting
        if ($configArea === 'admin') {
            if ((string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH)) {
                $customUrl = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_CUSTOM_ADMIN_PATH);
                $xmlPath = Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME;
                if ((string) Mage::getConfig()->getNode($xmlPath) !== $customUrl) {
                    Mage::getConfig()->setNode($xmlPath, $customUrl, true);
                }
            }
        }

        $adminFrontName = self::getAdminFrontName();

        foreach ($routersConfigNode->children() as $routerName => $routerConfig) {
            $use = (string) $routerConfig->use;
            if ($use !== $useRouterName) {
                continue;
            }

            $baseModule = (string) $routerConfig->args->module;
            if (!$baseModule) {
                continue;
            }

            $frontName = (string) $routerConfig->args->frontName;
            if (!$frontName) {
                continue;
            }

            // Admin router: only accept modules under the configured admin frontName
            if ($configArea === 'admin' && $frontName !== $adminFrontName) {
                continue;
            }

            $modules = self::buildModuleChain($routerConfig, $baseModule);

            // Merge into existing module chain (multiple config entries can contribute)
            if (isset(self::$modules[$frontName])) {
                self::$modules[$frontName] = array_unique(
                    array_merge(self::$modules[$frontName], $modules),
                );
            } else {
                self::$modules[$frontName] = $modules;
            }
            self::$routes[(string) $routerName] = $frontName;
        }
    }

    /**
     * Build the module chain with before/after ordering.
     *
     * @return string[]
     */
    protected static function buildModuleChain(mixed $routerConfig, string $baseModule): array
    {
        $modules = [$baseModule];

        if (!$routerConfig->args->modules) {
            return $modules;
        }

        foreach ($routerConfig->args->modules->children() as $customModule) {
            $moduleName = (string) $customModule;
            if (!$moduleName) {
                continue;
            }

            if ($before = $customModule->getAttribute('before')) {
                $position = array_search($before, $modules);
                if ($position === false) {
                    $position = 0;
                }
                array_splice($modules, $position, 0, $moduleName);
            } elseif ($after = $customModule->getAttribute('after')) {
                $position = array_search($after, $modules);
                if ($position === false) {
                    $position = count($modules);
                }
                array_splice($modules, $position + 1, 0, $moduleName);
            } else {
                $modules[] = $moduleName;
            }
        }

        return $modules;
    }

    /**
     * Get the frontName for a route name. e.g. 'catalog' → 'catalog', 'adminhtml' → 'admin'
     */
    public static function getFrontNameByRoute(string $routeName): ?string
    {
        self::ensureInitialized();
        return self::$routes[$routeName] ?? null;
    }

    /**
     * Get the route name for a frontName. e.g. 'catalog' → 'catalog', 'admin' → 'adminhtml'
     */
    public static function getRouteByFrontName(string $frontName): ?string
    {
        self::ensureInitialized();
        $result = array_search($frontName, self::$routes, true);
        return $result !== false ? (string) $result : null;
    }

    /**
     * Get the module chain for a frontName. e.g. 'catalog' → ['Mage_Catalog']
     *
     * @return string[]|null
     */
    public static function getModulesByFrontName(string $frontName): ?array
    {
        self::ensureInitialized();
        return self::$modules[$frontName] ?? null;
    }

    /**
     * Get the configured admin frontName (e.g. 'admin' or custom value).
     */
    public static function getAdminFrontName(): string
    {
        return RouteCollectionBuilder::getAdminFrontNameStatic();
    }

    protected static function ensureInitialized(): void
    {
        if (!self::$initialized) {
            self::init();
        }
    }
}
