<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use Maho\ApiPlatform\Service\StoreContext;

/**
 * Lists the attributes that have their own value in the store view of the request.
 *
 * The data comes from the "exists store value" flags that the catalog EAV load sets
 * for each value row of the store view (a row with a NULL value included). The admin
 * "Use Default Value" checkboxes use the same flags.
 */
final class StoreOverrides
{
    /**
     * Attribute codes with a store view value on a loaded catalog model, in the format
     * that useDefault accepts. Returns null when the request has no explicit store
     * view context (no ?store=, or ?store=admin), or when the model was loaded for a
     * different store.
     *
     * @return list<string>|null
     */
    public static function forModel(\Mage_Catalog_Model_Abstract $model): ?array
    {
        $storeId = StoreContext::getExplicitStoreId();
        if ($storeId === null
            || $storeId === \Mage_Core_Model_App::ADMIN_STORE_ID
            || (int) $model->getStoreId() !== $storeId
        ) {
            return null;
        }
        $resource = $model->getResource();
        if (!$resource instanceof \Mage_Catalog_Model_Resource_Abstract) {
            return null;
        }

        $codes = [];
        foreach ($resource->getAttributesByCode() as $code => $attribute) {
            // useDefault accepts only non-static attributes that are not global.
            if (!$attribute instanceof \Mage_Catalog_Model_Resource_Eav_Attribute
                || $attribute->isScopeGlobal()
                || $attribute->getBackend()->isStatic()
            ) {
                continue;
            }
            if ($model->getExistsStoreValueFlag($code)) {
                $codes[] = (string) $code;
            }
        }
        sort($codes);

        return $codes;
    }
}
