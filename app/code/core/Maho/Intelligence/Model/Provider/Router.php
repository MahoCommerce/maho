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
     * Get all route definitions across frontend and admin areas
     */
    public function getAllRoutes(): array
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
}
