<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;

class StoreProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $stores = [];

        foreach (\Mage::app()->getStores(false) as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            $stores[] = Store::fromModel($store);
        }

        return $stores;
    }
}
