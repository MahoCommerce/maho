<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class ProductTierPriceProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProductForRead($productId);

        $tierPrices = $product->getTierPrice();
        if (!is_array($tierPrices)) {
            return [];
        }

        // Frontend callers only see the rows that price them: their own
        // customer group (or the all-groups row) on the current website. The
        // full matrix is backend data and needs an actual products grant.
        $backend = $this->canReadBackendProducts();
        $callerGroupId = $this->getCustomerGroupId();
        $websiteId = (int) StoreContext::getStore()->getWebsiteId();

        $result = [];
        $i = 0;
        foreach ($tierPrices as $tp) {
            $rowGroupId = (int) ($tp['cust_group'] ?? \Mage_Customer_Model_Group::CUST_GROUP_ALL);
            $rowWebsiteId = (int) ($tp['website_id'] ?? 0);
            if (!$backend) {
                if ($rowGroupId !== \Mage_Customer_Model_Group::CUST_GROUP_ALL && $rowGroupId !== $callerGroupId) {
                    continue;
                }
                if ($rowWebsiteId !== 0 && $rowWebsiteId !== $websiteId) {
                    continue;
                }
            }
            $dto = new ProductTierPrice();
            $dto->id = $productId . '_' . $i++;
            $dto->customerGroupId = $rowGroupId === \Mage_Customer_Model_Group::CUST_GROUP_ALL ? 'all' : $rowGroupId;
            $dto->websiteId = $rowWebsiteId;
            $dto->qty = (float) ($tp['price_qty'] ?? 1);
            $dto->price = (float) ($tp['price'] ?? 0);
            $result[] = $dto;
        }

        return $result;
    }
}
