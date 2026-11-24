<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

/**
 * @phpstan-type XmlRouter array{name: string, area: string, type: string, module: string, front_name: string, module_overrides?: array<string, array{module: string, before?: string, after?: string}>}
 * @phpstan-type AttributeRoute array{name: string, area: string|null, path: string, methods: list<string>, class: string, action: string, module: string|null, controller_name: string|null, path_variables: list<string>, defaults: array<string, string|int|float|bool|null>, requirements: array<string, string>}
 */
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
     * @return array{xml_routers: array<string, XmlRouter>, attribute_routes: array<string, AttributeRoute>}
     */
    public function getAllRoutes(): array
    {
        return [
            'xml_routers' => $this->getXmlRouters(),
            'attribute_routes' => $this->getAttributeRoutes(),
        ];
    }

    /**
     * @return array<string, XmlRouter>
     */
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

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * @return array<string, AttributeRoute>
     */
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
                'path_variables' => $route['pathVariables'] ?? [],
                'defaults' => $route['defaults'] ?? [],
                'requirements' => $route['requirements'] ?? [],
            ];
        }

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }
}
