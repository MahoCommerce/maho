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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDeleteAll($productId, $user);
        }

        return $this->handleReplace($productId, $context, $user);
    }

    private function handleReplace(int $productId, array $context, \Maho\ApiPlatform\Security\ApiUser $user): array
    {
        // Load in the admin/default (store 0) scope: the group-price backend
        // only reconciles (deletes removed rows) against the scope the product
        // is loaded in, so a store-view load would leave global rows behind and
        // a "replace" would silently accumulate instead of replacing.
        $product = $this->loadProductForGlobalPrice($productId);

        // Store-restricted tokens may only price websites they're scoped to;
        // null means unrestricted (any website, including 0 = all).
        $allowedWebsiteIds = $this->getAllowedWebsiteIds($user);

        // This endpoint takes a top-level JSON array of group prices (not the
        // object-with-fields shape parseRequestBody() normalises for), and must
        // reject a non-array body rather than silently treat it as empty.
        $request = $context['request'] ?? null;
        try {
            $body = $request ? \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]') : [];
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        if (!is_array($body)) {
            throw new BadRequestHttpException('Request body must be an array of group prices');
        }

        $groupPrices = [];
        foreach ($body as $gp) {
            if (!is_array($gp)) {
                throw new BadRequestHttpException('Each group price must be an object');
            }

            $groupId = $gp['customerGroupId'] ?? $gp['customer_group_id'] ?? 'all';
            if ($groupId === 'all') {
                $groupId = \Mage_Customer_Model_Group::CUST_GROUP_ALL;
            }

            $price = (float) ($gp['price'] ?? 0);
            if ($price < 0) {
                throw new BadRequestHttpException('Price must not be negative');
            }

            $websiteId = (int) ($gp['websiteId'] ?? $gp['website_id'] ?? 0);
            if ($allowedWebsiteIds !== null && !in_array($websiteId, $allowedWebsiteIds, true)) {
                throw new AccessDeniedHttpException('Access denied for the requested group-price website');
            }

            $groupPrices[] = [
                'website_id' => $websiteId,
                'cust_group' => (int) $groupId,
                'price' => $price,
            ];
        }

        $this->replaceGroupPrices($product, $groupPrices, $allowedWebsiteIds);

        // Re-read and return
        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId],
            [],
        );
    }

    private function handleDeleteAll(int $productId, \Maho\ApiPlatform\Security\ApiUser $user): null
    {
        $product = $this->loadProductForGlobalPrice($productId);
        $this->replaceGroupPrices($product, [], $this->getAllowedWebsiteIds($user));

        return null;
    }

    /**
     * True replace scoped to the caller's websites. The backend's afterLoad sets
     * both data and origData to the full existing rows (price_id included), so
     * one save reconciles insert/update/delete; rows outside a restricted token's
     * website allowlist are carried into the new set instead of wiped.
     *
     * @param array<array<string, mixed>> $groupPrices
     * @param array<int>|null $allowedWebsiteIds
     */
    private function replaceGroupPrices(\Mage_Catalog_Model_Product $product, array $groupPrices, ?array $allowedWebsiteIds): void
    {
        $existing = $this->loadExistingGroupPrices($product);

        if ($allowedWebsiteIds !== null) {
            $preserved = array_values(array_filter(
                $existing,
                static fn(array $row): bool => !in_array((int) ($row['website_id'] ?? 0), $allowedWebsiteIds, true),
            ));
            $groupPrices = array_merge($preserved, $groupPrices);
        }

        $product->setData('group_price', $groupPrices);
        $this->safeSave($product, 'save group prices');
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function loadExistingGroupPrices(\Mage_Catalog_Model_Product $product): array
    {
        $rows = $product->getData('group_price');
        if ($rows === null) {
            $attribute = $product->getResource()->getAttribute('group_price');
            if ($attribute) {
                $attribute->getBackend()->afterLoad($product);
                $rows = $product->getData('group_price');
            }
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Load a product in the admin/default (store 0) scope so global group-price
     * reconciliation deletes removed rows on save (see handleReplace).
     */
    private function loadProductForGlobalPrice(int $productId): \Mage_Catalog_Model_Product
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product');
        $product->setStoreId(0);
        $product->load($productId);
        if (!$product->getId()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Product not found');
        }
        return $product;
    }
}
