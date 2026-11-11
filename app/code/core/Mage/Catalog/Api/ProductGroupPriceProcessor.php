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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
            foreach (self::parsePriceRows($context['request'] ?? null, 'group price') as $row) {
                $rows[] = [
                    'website_id' => (int) ($row['websiteId'] ?? $row['website_id'] ?? 0),
                    'cust_group' => self::parseGroupId($row),
                    'price' => (float) ($row['price'] ?? 0),
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

    /**
     * Read the body of a price endpoint: a JSON array of objects, not the object that parseRequestBody() reads.
     *
     * @return list<array<string, mixed>>
     * @throws BadRequestHttpException when the body is not a JSON array of objects
     */
    public static function parsePriceRows(?Request $request, string $label): array
    {
        try {
            $body = $request ? \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]') : [];
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }
        if (!is_array($body)) {
            throw new BadRequestHttpException("Request body must be an array of {$label}s");
        }
        foreach ($body as $row) {
            if (!is_array($row)) {
                throw new BadRequestHttpException("Each {$label} must be an object");
            }
        }
        return array_values($body);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function parseGroupId(array $row): int
    {
        $groupId = $row['customerGroupId'] ?? $row['customer_group_id'] ?? 'all';
        return $groupId === 'all' ? \Mage_Customer_Model_Group::CUST_GROUP_ALL : (int) $groupId;
    }
}
