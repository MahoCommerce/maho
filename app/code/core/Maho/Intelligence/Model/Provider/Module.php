<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Module
{
    /**
     * Get all active modules with versions and dependencies
     */
    public function getAllModules(): array
    {
        $modulesNode = Mage::getConfig()->getNode('modules');
        if (!$modulesNode) {
            return [];
        }

        $result = [];
        foreach ($modulesNode->children() as $moduleName => $moduleConfig) {
            $active = (string) ($moduleConfig->active ?? 'false');
            if (!in_array(strtolower($active), ['true', '1'])) {
                continue;
            }

            $depends = [];
            if (isset($moduleConfig->depends)) {
                foreach ($moduleConfig->depends->children() as $dep) {
                    $depends[] = $dep->getName();
                }
            }

            $result[$moduleName] = [
                'name' => $moduleName,
                'version' => (string) ($moduleConfig->version ?? ''),
                'code_pool' => (string) ($moduleConfig->codePool ?? ''),
                'depends' => $depends,
            ];
        }

        ksort($result);
        return $result;
    }
}
