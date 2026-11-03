<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class ProductTierPriceProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductTierPriceProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);
        $this->assertProductWebsitesAllowed($product, $user);

        $rows = [];
        if (!$operation instanceof DeleteOperationInterface) {
            foreach (ProductGroupPriceProcessor::parsePriceRows($context['request'] ?? null, 'tier price') as $row) {
                $rows[] = [
                    'website_id' => (int) ($row['websiteId'] ?? $row['website_id'] ?? 0),
                    'cust_group' => ProductGroupPriceProcessor::parseGroupId($row),
                    'price_qty' => (float) ($row['qty'] ?? 1),
                    'price' => (float) ($row['price'] ?? 0),
                ];
            }
        }

        /** @var \Mage_Catalog_Service_Product_Price $service */
        $service = \Mage::getService('catalog/product_price');
        $service->setTierPrices($product, $rows, $this->getAllowedWebsiteIds($user));

        return $operation instanceof DeleteOperationInterface
            ? null
            : $this->provider->provide(new GetCollection(), ['productId' => $productId], []);
    }
}
