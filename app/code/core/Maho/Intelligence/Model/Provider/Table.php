<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Table
{
    /**
     * Get all database table name mappings from resource model config
     */
    public function getAllTables(): array
    {
        $config = Mage::getConfig();
        $modelsNode = $config->getNode('global/models');
        if (!$modelsNode) {
            return [];
        }

        $result = [];
        foreach ($modelsNode->children() as $group => $groupConfig) {
            if (!isset($groupConfig->entities)) {
                continue;
            }

            foreach ($groupConfig->entities->children() as $entity) {
                $entityName = $entity->getName();
                $tableName = (string) ($entity->table ?? '');
                if (empty($tableName)) {
                    continue;
                }

                $alias = "{$group}/{$entityName}";
                $result[$alias] = [
                    'alias' => $alias,
                    'table' => $tableName,
                    'group' => $group,
                ];
            }
        }

        ksort($result);
        return $result;
    }
}
