<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
