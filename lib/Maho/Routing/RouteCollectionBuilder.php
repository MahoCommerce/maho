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
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Assembles the Symfony RouteCollection from compiled #[Route] attributes.
 *
 * Routes are compiled at `composer dump-autoload` and loaded at runtime.
 * Unmatched requests fall back to the legacy router loop (CMS, Blog, Default routers).
 */
class RouteCollectionBuilder
{
    protected const REGISTRY_KEY = '_maho_route_collection';

    /**
     * Reverse lookup: "frontName/controllerName/actionName" → route info.
     *
     * @var array<string, array{name: string, path: string, pathVariables: string[], area: string}>|null
     */
    protected static ?array $_reverseLookup = null;

    /**
     * Build or retrieve the cached RouteCollection for the current request.
     */
    public function build(): RouteCollection
    {
        $cached = Mage::registry(self::REGISTRY_KEY);
        if ($cached instanceof RouteCollection) {
            return $cached;
        }

        $collection = new RouteCollection();

        $this->loadAttributeRoutes($collection);

        Mage::register(self::REGISTRY_KEY, $collection);

        return $collection;
    }

    /**
     * Load routes from compiled #[Route] attributes.
     *
     * Attribute routes are specific (exact paths) and take priority over
     * XML catch-all routes. These will be populated once the composer plugin
     * compiler is updated and controllers are migrated (Phase 3).
     */
    protected function loadAttributeRoutes(RouteCollection $collection): void
    {
        $compiled = \Maho::getCompiledAttributes();
        $routes = $compiled['routes'] ?? [];
        $adminFrontName = $this->getAdminFrontName();

        foreach ($routes as $name => $routeData) {
            $path = $routeData['path'];

            $area = $routeData['area'] ?? 'frontend';
            $defaults = $routeData['defaults'] ?? [];
            $requirements = $routeData['requirements'] ?? [];

            // Admin routes are compiled with '/admin/' prefix — replace with actual admin frontName
            if ($area === 'adminhtml' && $adminFrontName !== 'admin') {
                $path = preg_replace('#^/admin(/|$)#', '/' . $adminFrontName . '$1', $path);
            }

            // Admin routes: auto-append catch-all for key/value URL params (e.g. /id/5/store/1)
            if ($area === 'adminhtml' && !str_contains($path, '{_catchall}')) {
                $path = rtrim($path, '/') . '/{_catchall}';
                $defaults['_catchall'] = '';
                $requirements['_catchall'] = '.*';
            }

            $route = new \Symfony\Component\Routing\Route(
                $path,
                array_merge($defaults, [
                    '_maho_type' => 'attribute',
                    '_maho_controller' => $routeData['class'],
                    '_maho_action' => $routeData['action'],
                    '_maho_area' => $area,
                    '_maho_module' => $routeData['module'] ?? '',
                    '_maho_controller_name' => $routeData['controllerName'] ?? '',
                ]),
                $requirements,
            );

            if (!empty($routeData['methods'])) {
                $route->setMethods($routeData['methods']);
            }

            $collection->add($name, $route);
        }
    }

    /**
     * Resolve a Symfony route from legacy path components (frontName/controller/action).
     *
     * @return array{name: string, path: string, pathVariables: string[]}|null
     */
    public static function resolveRoute(string $frontName, string $controllerName, string $actionName): ?array
    {
        if (self::$_reverseLookup === null) {
            self::buildReverseLookup();
        }

        $key = strtolower($frontName . '/' . $controllerName . '/' . $actionName);
        return self::$_reverseLookup[$key] ?? null;
    }

    protected static function buildReverseLookup(): void
    {
        self::$_reverseLookup = [];
        $compiled = \Maho::getCompiledAttributes();
        $routes = $compiled['routes'] ?? [];
        $adminFrontName = self::getAdminFrontNameStatic();

        foreach ($routes as $name => $routeData) {
            $path = $routeData['path'];
            $area = $routeData['area'] ?? 'frontend';

            // Admin routes: replace '/admin/' prefix with actual admin frontName
            if ($area === 'adminhtml' && $adminFrontName !== 'admin') {
                $path = preg_replace('#^/admin(/|$)#', '/' . $adminFrontName . '$1', $path);
            }

            $segments = explode('/', ltrim($path, '/'));
            $frontName = $segments[0] ?? '';

            $controllerName = $routeData['controllerName'] ?? 'index';
            $action = preg_replace('/Action$/', '', $routeData['action'] ?? 'indexAction');

            preg_match_all('/\{(\w+)\}/', $path, $matches);
            $pathVariables = $matches[1];

            $key = strtolower($frontName . '/' . $controllerName . '/' . $action);

            // First route wins (attribute routes have priority)
            if (!isset(self::$_reverseLookup[$key])) {
                self::$_reverseLookup[$key] = [
                    'name' => $name,
                    'path' => $path,
                    'pathVariables' => $pathVariables,
                    'area' => $area,
                ];
            }
        }
    }

    protected function getAdminFrontName(): string
    {
        return self::getAdminFrontNameStatic();
    }

    public static function getAdminFrontNameStatic(): string
    {
        if ((string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH)) {
            $customUrl = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_CUSTOM_ADMIN_PATH);
            if ($customUrl !== '') {
                return $customUrl;
            }
        }

        return (string) Mage::getConfig()->getNode(
            Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME,
        );
    }

    /**
     * Generate a URL for a named route.
     *
     * @return string|null The generated path, or null if the route doesn't exist
     */
    public static function generateUrl(string $name, array $params = []): ?string
    {
        try {
            $collection = (new self())->build();
            $context = new RequestContext();
            $request = Mage::app()->getRequest();
            if (method_exists($request, 'getSymfonyRequest')) {
                $context->fromRequest($request->getSymfonyRequest());
            }
            $generator = new UrlGenerator($collection, $context);
            return $generator->generate($name, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
