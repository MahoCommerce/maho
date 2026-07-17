<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StoreProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Store|null
    {
        // Single-store read: /stores/{id}. Load the model directly rather than
        // Mage::app()->getStore(), which throws Mage_Core_Model_Store_Exception
        // ("Invalid store code requested.") for an unknown id — surfacing as a
        // 422 instead of the expected 404.
        if (isset($uriVariables['id'])) {
            $store = $this->loadById('core/store', (int) $uriVariables['id']);
            if (!$store->getId() || !$store->getIsActive()) {
                throw new NotFoundHttpException('Store not found');
            }
            return Store::fromModel($store);
        }

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
