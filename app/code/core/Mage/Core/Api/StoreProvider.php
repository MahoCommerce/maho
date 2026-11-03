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
    /**
     * @return list<Store>|Store
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Store
    {
        /** @var \Mage_Core_Service_Store $service */
        $service = \Mage::getService('core/store');
        if (isset($uriVariables['id'])) {
            return Store::fromModel($service->getActiveStore((int) $uriVariables['id']));
        }
        return array_map(Store::fromModel(...), $service->getActiveStores());
    }
}
