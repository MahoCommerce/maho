<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Store
{
    /**
     * Get the full website → store group → store view hierarchy
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
