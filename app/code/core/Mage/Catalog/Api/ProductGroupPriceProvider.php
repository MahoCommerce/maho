<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class ProductGroupPriceProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        $groupPrices = $product->getData('group_price');
        if ($groupPrices === null) {
            $attribute = $product->getResource()->getAttribute('group_price');
            if ($attribute) {
                $attribute->getBackend()->afterLoad($product);
                $groupPrices = $product->getData('group_price');
            }
        }
        if (!is_array($groupPrices)) {
            return [];
        }

        $result = [];
        $i = 0;
        foreach ($groupPrices as $gp) {
            $dto = new ProductGroupPrice();
            $dto->id = $productId . '_' . $i++;
            $dto->customerGroupId = (int) ($gp['cust_group'] ?? \Mage_Customer_Model_Group::CUST_GROUP_ALL) === \Mage_Customer_Model_Group::CUST_GROUP_ALL
                ? 'all'
                : (int) $gp['cust_group'];
            $dto->websiteId = (int) ($gp['website_id'] ?? 0);
            $dto->price = (float) ($gp['price'] ?? 0);
            $result[] = $dto;
        }

        return $result;
    }
}
