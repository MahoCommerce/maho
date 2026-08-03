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

final class ProductGroupPriceProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProductForRead($productId);

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

        // Frontend callers only see the rows that price them: their own
        // customer group (or the all-groups row) on the current website. The
        // full matrix is backend data and needs an actual products grant.
        $backend = $this->canReadBackendProducts();
        $callerGroupId = $this->getCustomerGroupId();
        $websiteId = (int) StoreContext::getStore()->getWebsiteId();

        $result = [];
        $i = 0;
        foreach ($groupPrices as $gp) {
            $rowGroupId = (int) ($gp['cust_group'] ?? \Mage_Customer_Model_Group::CUST_GROUP_ALL);
            $rowWebsiteId = (int) ($gp['website_id'] ?? 0);
            if (!$backend) {
                if ($rowGroupId !== \Mage_Customer_Model_Group::CUST_GROUP_ALL && $rowGroupId !== $callerGroupId) {
                    continue;
                }
                if ($rowWebsiteId !== 0 && $rowWebsiteId !== $websiteId) {
                    continue;
                }
            }
            $dto = new ProductGroupPrice();
            $dto->id = $productId . '_' . $i++;
            $dto->customerGroupId = $rowGroupId === \Mage_Customer_Model_Group::CUST_GROUP_ALL ? 'all' : $rowGroupId;
            $dto->websiteId = $rowWebsiteId;
            $dto->price = (float) ($gp['price'] ?? 0);
            $result[] = $dto;
        }

        return $result;
    }
}
