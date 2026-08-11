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
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDeleteAll($productId);
        }

        return $this->handleReplace($productId, $context, $user);
    }

    private function handleReplace(int $productId, array $context, \Maho\ApiPlatform\Security\ApiUser $user): array
    {
        // Load in the admin/default (store 0) scope. Tier prices are global
        // (website 0) here, and the group-price backend only reconciles — i.e.
        // deletes removed rows — against the scope the product is loaded in.
        // Loading in a store-view scope leaves the previous global rows behind,
        // so a "replace" would silently accumulate instead of replacing.
        $product = $this->loadProductForGlobalPrice($productId);

        // Store-restricted tokens may only price websites they're scoped to;
        // null means unrestricted (any website, including 0 = all).
        $allowedWebsiteIds = $this->getAllowedWebsiteIds($user);

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

            $websiteId = (int) ($tp['websiteId'] ?? $tp['website_id'] ?? 0);
            if ($allowedWebsiteIds !== null && !in_array($websiteId, $allowedWebsiteIds, true)) {
                throw new AccessDeniedHttpException('Access denied for the requested tier-price website');
            }

            $tierPrices[] = [
                'website_id' => $websiteId,
                'cust_group' => (int) $groupId,
                'price_qty' => $qty,
                'price' => $price,
            ];
        }

        // True replace, in two steps. A minimally-loaded product carries no
        // existing tier prices in origData, so a single save of the new set would
        // leave the previous rows behind (the group-price backend only deletes
        // rows present in "old"). Saving an empty set first makes the backend
        // clear every existing tier price (its documented empty-set behaviour),
        // then the second save inserts the new set onto a clean slate.
        if (!empty($tierPrices)) {
            $product->setTierPrice([]);
            $this->safeSave($product, 'save tier prices');
        }
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
        $product = $this->loadProductForGlobalPrice($productId);
        $product->getTierPrice();
        $product->setTierPrice([]);
        $this->safeSave($product, 'delete tier prices');

        return null;
    }

    /**
     * Load a product in the admin/default (store 0) scope so global tier-price
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
