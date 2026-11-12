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
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class ProductGroupPriceProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductGroupPriceProvider $provider,
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
            foreach (RequestInput::decodeRows($context['request'] ?? null, 'group price') as $row) {
                $rows[] = [
                    'website_id' => $row->int('websiteId', 'website_id') ?? 0,
                    'cust_group' => self::parseGroupId($row),
                    'price' => $row->float('price') ?? 0.0,
                ];
            }
        }

        /** @var \Mage_Catalog_Service_Product_Price $service */
        $service = \Mage::getService('catalog/product_price');
        $service->setGroupPrices($product, $rows, $this->allowedWebsiteIds($user));

        return $operation instanceof DeleteOperationInterface
            ? null
            : $this->provider->provide(new GetCollection(), ['productId' => $productId], []);
    }

    public static function parseGroupId(RequestInput $row): int
    {
        return ($row->string('customerGroupId', 'customer_group_id') ?? 'all') === 'all'
            ? \Mage_Customer_Model_Group::CUST_GROUP_ALL
            : (int) $row->int('customerGroupId', 'customer_group_id');
    }
}
