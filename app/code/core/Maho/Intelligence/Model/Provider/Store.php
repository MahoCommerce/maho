<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Store
{
    /**
     * Get the full website → store group → store view hierarchy
     *
     * @return list<array{website_id: int, code: string, name: string, is_default: bool, default_group_id: int, groups: list<array{group_id: int, name: string, default_store_id: int, stores: list<array{store_id: int, code: string, name: string, is_active: bool}>}>}>
     */
    public function getHierarchy(): array
    {
        $app = Mage::app();
        $result = [];

        foreach ($app->getWebsites() as $website) {
            $websiteData = [
                'website_id' => (int) $website->getId(),
                'code' => $website->getCode(),
                'name' => $website->getName(),
                'is_default' => (bool) $website->getIsDefault(),
                'default_group_id' => (int) $website->getDefaultGroupId(),
                'groups' => [],
            ];

            foreach ($website->getGroups() as $group) {
                $groupData = [
                    'group_id' => (int) $group->getId(),
                    'name' => $group->getName(),
                    'default_store_id' => (int) $group->getDefaultStoreId(),
                    'stores' => [],
                ];

                foreach ($group->getStores() as $store) {
                    $groupData['stores'][] = [
                        'store_id' => (int) $store->getId(),
                        'code' => $store->getCode(),
                        'name' => $store->getName(),
                        'is_active' => (bool) $store->getIsActive(),
                    ];
                }

                $websiteData['groups'][] = $groupData;
            }

            $result[] = $websiteData;
        }

        return $result;
    }
}
