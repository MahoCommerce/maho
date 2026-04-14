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
     * Called once during front controller init.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$modules = [];
        self::$routes = [];

        // Apply custom admin URL if configured, then register the adminhtml ↔ frontName mapping.
        if ((string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH)) {
            $customUrl = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_CUSTOM_ADMIN_PATH);
            $xmlPath = Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME;
            if ($customUrl !== '' && (string) Mage::getConfig()->getNode($xmlPath) !== $customUrl) {
                Mage::getConfig()->setNode($xmlPath, $customUrl, true);
            }
        }

        $adminFrontName = self::getAdminFrontName();
        if ($adminFrontName) {
            self::$routes['adminhtml'] = $adminFrontName;
            self::$modules[$adminFrontName] = self::buildAdminModuleChain();
        }

        self::$initialized = true;
    }

    public static function reset(): void
    {
        self::$modules = [];
        self::$routes = [];
        self::$initialized = false;
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

    /**
     * Build the admin module chain from config.xml, including third-party extensions.
     *
     * Third-party modules extend the admin router by adding entries under
     * admin/routers/adminhtml/args/modules with before/after ordering attributes.
     * This replicates the logic from the former Router_Standard::collectRoutes().
     *
     * @return string[]
     */
    protected static function buildAdminModuleChain(): array
    {
        $modules = ['Mage_Adminhtml'];

        // Third-party modules inject controllers via admin/routers/adminhtml/args/modules
        $modulesNode = Mage::getConfig()->getNode('admin/routers/adminhtml/args/modules');
        if (!$modulesNode) {
            return $modules;
        }

        foreach ($modulesNode->children() as $customModule) {
            $moduleName = (string) $customModule;
            if (!$moduleName) {
                continue;
            }

            if ($before = $customModule->getAttribute('before')) {
                $position = array_search($before, $modules, true);
                $position = ($position === false) ? 0 : $position;
                array_splice($modules, $position, 0, $moduleName);
            } elseif ($after = $customModule->getAttribute('after')) {
                $position = array_search($after, $modules, true);
                $position = ($position === false) ? count($modules) : $position + 1;
                array_splice($modules, $position, 0, $moduleName);
            } else {
                $modules[] = $moduleName;
            }
        }

        return $modules;
    }

    protected static function ensureInitialized(): void
    {
        if (!self::$initialized) {
            self::init();
        }
    }
}
