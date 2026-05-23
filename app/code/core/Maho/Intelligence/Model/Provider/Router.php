<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Router
{
    /**
     * Get all routing definitions.
     *
     * Returns two sections: XML routers are router-level frontName→module
     * mappings (with optional controller-override chains); attribute routes
     * are per-URL Symfony routes compiled from #[Maho\Config\Route]
     * attributes into vendor/composer/maho_attributes.php.
     *
     * @return array{xml_routers: array, attribute_routes: array}
     */
    public function getAllRoutes(): array
    {
        return [
            'xml_routers' => $this->getXmlRouters(),
            'attribute_routes' => $this->getAttributeRoutes(),
        ];
    }

    private function getXmlRouters(): array
    {
        $config = Mage::getConfig();
        $result = [];

        foreach (['frontend', 'admin'] as $area) {
            $routersNode = $config->getNode("{$area}/routers");
            if (!$routersNode) {
                continue;
            }

            foreach ($routersNode->children() as $routerName => $routerConfig) {
                $route = [
                    'name' => $routerName,
                    'area' => $area,
                    'type' => (string) ($routerConfig->use ?? ''),
                    'module' => (string) ($routerConfig->args->module ?? ''),
                    'front_name' => (string) ($routerConfig->args->frontName ?? ''),
                ];

                if (isset($routerConfig->args->modules)) {
                    $overrides = [];
                    foreach ($routerConfig->args->modules->children() as $override) {
                        $entry = [
                            'module' => (string) $override,
                        ];
                        if ($override->getAttribute('before')) {
                            $entry['before'] = $override->getAttribute('before');
                        }
                        if ($override->getAttribute('after')) {
                            $entry['after'] = $override->getAttribute('after');
                        }
                        $overrides[$override->getName()] = $entry;
                    }
                    if (!empty($overrides)) {
                        $route['module_overrides'] = $overrides;
                    }
                }

                $result["{$area}/{$routerName}"] = $route;
            }
        }

        ksort($result);
        return $result;
    }

    private function getAttributeRoutes(): array
    {
        $routes = Maho::getCompiledAttributes()['routes'] ?? [];
        $result = [];

        foreach ($routes as $routeName => $route) {
            $result[$routeName] = [
                'name' => $routeName,
                'area' => $route['area'] ?? null,
                'path' => $route['path'] ?? '',
                'methods' => $route['methods'] ?? [],
                'class' => $route['class'] ?? '',
                'action' => $route['action'] ?? '',
                'module' => $route['module'] ?? null,
                'controller_name' => $route['controllerName'] ?? null,
                'defaults' => $route['defaults'] ?? [],
                'requirements' => $route['requirements'] ?? [],
            ];
        }

        ksort($result);
        return $result;
    }
}
