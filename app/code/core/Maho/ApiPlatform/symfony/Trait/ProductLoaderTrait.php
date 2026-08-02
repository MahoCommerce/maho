<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Status;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Loads a catalog product by ID within the current store context.
 *
 * Handles StoreContext initialization, store-scoped loading, and not-found
 * checks. Supports an optional product type constraint for typed sub-resources
 * (bundle, configurable, grouped, downloadable).
 *
 * Used by all product sub-resource providers and processors to replace 16
 * identical copies of the same loadProduct() method.
 */
trait ProductLoaderTrait
{
    use StoreAccessTrait;

    protected function loadProduct(int $id, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        if ($storeId) {
            $product->setStoreId($storeId);
        }
        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($requiredType !== null && $product->getTypeId() !== $requiredType) {
            throw new BadRequestHttpException("Product is not a {$requiredType} product");
        }

        return $product;
    }

    /**
     * Load for a public sub-resource read: non-back-office callers only reach
     * enabled products on the current store's website (same rule as
     * ProductProvider::loadProductDto), so hidden products 404 before the
     * type check can disclose them.
     */
    protected function loadProductForRead(int $id, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        $product = $this->loadProduct($id);

        if ($this->canReadBackOfficeProducts()) {
            if ($this->isApiUser()) {
                $this->authorizeProductWebsites($product, $this->getAuthorizedUser());
            }
        } else {
            $websiteId = (int) StoreContext::getStore()->getWebsiteId();
            if (!in_array($websiteId, array_map('intval', $product->getWebsiteIds()), true)
                || (int) $product->getStatus() !== Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            ) {
                throw new NotFoundHttpException('Product not found');
            }
        }

        if ($requiredType !== null && $product->getTypeId() !== $requiredType) {
            throw new BadRequestHttpException("Product is not a {$requiredType} product");
        }

        return $product;
    }

    /**
     * Admin tokens, or an API user holding a products grant (write counts so
     * an integration can read back what it writes).
     */
    protected function canReadBackOfficeProducts(): bool
    {
        return $this->isBackOffice('products');
    }

    /**
     * Load for a model save: global scope unless the request names a store.
     * Saving a product loaded at a store view clones every store-scope attribute
     * into that store (the _canUpdateAttribute store-view rule), so writes must
     * not inherit the read context's default store. resolveWriteScope() keeps
     * store-restricted tokens off the global scope.
     */
    protected function loadProductForWrite(int $id, ApiUser $user, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        $storeId = $this->resolveWriteScope($user);

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($storeId);
        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($requiredType !== null && $product->getTypeId() !== $requiredType) {
            throw new BadRequestHttpException("Product is not a {$requiredType} product");
        }

        return $product;
    }

    /**
     * Map a store-restricted API user's allowed STORE ids to their website ids.
     *
     * Returns null when the user is unrestricted (getAllowedStoreIds() === null),
     * signalling "no website restriction": callers must treat null as "allow all".
     * Single source of truth for the store-to-website scope shared by the main
     * product CRUD (ProductProcessor) and every product sub-resource processor.
     *
     * @return int[]|null
     */
    protected function getAllowedWebsiteIds(ApiUser $user): ?array
    {
        $allowedStoreIds = $user->getAllowedStoreIds();
        if ($allowedStoreIds === null) {
            return null;
        }

        $websiteIds = [];
        foreach ($allowedStoreIds as $storeId) {
            try {
                $websiteIds[] = (int) Mage::app()->getStore($storeId)->getWebsiteId();
            } catch (\Mage_Core_Model_Store_Exception) {
                // deleted or unknown store: grants access to no website
            }
        }

        return array_values(array_unique($websiteIds));
    }

    /**
     * Authorize a loaded product against a store-restricted API user: the product
     * must belong to at least one website the user's allowed stores map to. No-op
     * for unrestricted users (getAllowedStoreIds() === null). Applied identically
     * by the main product CRUD and sub-resource writes (custom options, media,
     * tier prices, links, bundle/configurable setup, …), so none can be used to
     * reach a product outside the user's website scope.
     */
    protected function authorizeProductWebsites(Mage_Catalog_Model_Product $product, ApiUser $user): void
    {
        $allowedWebsiteIds = $this->getAllowedWebsiteIds($user);
        if ($allowedWebsiteIds === null) {
            return;
        }

        $productWebsiteIds = array_map('intval', $product->getWebsiteIds());

        if (array_intersect($productWebsiteIds, $allowedWebsiteIds) === []) {
            throw new AccessDeniedHttpException("Access denied for this product's websites");
        }
    }
}
