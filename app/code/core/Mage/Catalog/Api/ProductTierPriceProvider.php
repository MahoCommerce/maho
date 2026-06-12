<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class ProductTierPriceProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        $tierPrices = $product->getTierPrice();
        if (!is_array($tierPrices)) {
            return [];
        }

        $result = [];
        foreach ($tierPrices as $i => $tp) {
            $dto = new ProductTierPrice();
            $dto->id = $productId . '_' . $i;
            $dto->customerGroupId = (int) ($tp['cust_group'] ?? \Mage_Customer_Model_Group::CUST_GROUP_ALL) === \Mage_Customer_Model_Group::CUST_GROUP_ALL
                ? 'all'
                : (int) $tp['cust_group'];
            $dto->websiteId = (int) ($tp['website_id'] ?? 0);
            $dto->qty = (float) ($tp['price_qty'] ?? 1);
            $dto->price = (float) ($tp['price'] ?? 0);
            $result[] = $dto;
        }

        return $result;
    }
}
