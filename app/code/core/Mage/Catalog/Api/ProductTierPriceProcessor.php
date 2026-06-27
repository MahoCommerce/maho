<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 */
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
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->authorizeProductWebsites($this->loadProduct($productId), $user);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDeleteAll($productId);
        }

        $this->requirePermission($user, 'products/write');
        return $this->handleReplace($productId, $context);
    }

    private function handleReplace(int $productId, array $context): array
    {
        $product = $this->loadProduct($productId);

        // This endpoint takes a top-level JSON array of tier prices (not the
        // object-with-fields shape parseRequestBody() normalises for), and must
        // reject a non-array body rather than silently treat it as empty.
        $request = $context['request'] ?? null;
        try {
            $body = $request ? \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]') : [];
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        if (!is_array($body)) {
            throw new BadRequestHttpException('Request body must be an array of tier prices');
        }

        $tierPrices = [];
        foreach ($body as $tp) {
            if (!is_array($tp)) {
                throw new BadRequestHttpException('Each tier price must be an object');
            }

            $groupId = $tp['customerGroupId'] ?? $tp['customer_group_id'] ?? 'all';
            if ($groupId === 'all') {
                $groupId = \Mage_Customer_Model_Group::CUST_GROUP_ALL;
            }

            $price = (float) ($tp['price'] ?? 0);
            if ($price < 0) {
                throw new BadRequestHttpException('Price must not be negative');
            }

            $qty = (float) ($tp['qty'] ?? 1);
            if ($qty <= 0) {
                throw new BadRequestHttpException('Quantity must be greater than 0');
            }

            $tierPrices[] = [
                'website_id' => (int) ($tp['websiteId'] ?? $tp['website_id'] ?? 0),
                'cust_group' => (int) $groupId,
                'price_qty' => $qty,
                'price' => $price,
            ];
        }

        $product->getTierPrice();
        $product->setTierPrice($tierPrices);

        $this->safeSave($product, 'save tier prices');

        // Re-read and return
        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId],
            [],
        );
    }

    private function handleDeleteAll(int $productId): null
    {
        $product = $this->loadProduct($productId);
        $product->getTierPrice();
        $product->setTierPrice([]);
        $this->safeSave($product, 'delete tier prices');

        return null;
    }

}
