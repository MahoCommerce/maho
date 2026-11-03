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
use Mage_Catalog_Service_Product;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Load the product of a product sub-resource through Mage_Catalog_Service_Product, in the store of the request.
 * A typed sub-resource (bundle, configurable, grouped, downloadable) names the product type that it needs.
 */
trait ProductLoaderTrait
{
    use StoreAccessTrait;

    /**
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    protected function loadProduct(int $id, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        return $this->assertProductType(self::productService()->getById($id, StoreContext::getStoreId()), $requiredType);
    }

    /**
     * Load for a sub-resource read. A caller without backend access reaches only a product that the storefront of
     * the store shows, so a hidden product is not found before the type check can disclose it.
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     * @throws \Mage_Core_Exception_Forbidden
     */
    protected function loadProductForRead(int $id, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        $service = self::productService();
        if (!$this->canReadBackendProducts()) {
            return $this->assertProductType($service->getVisibleById($id, StoreContext::getStoreId()), $requiredType);
        }
        $product = $service->getById($id, StoreContext::getStoreId());
        if ($this->isApiUser()) {
            $service->assertInStores($product, $this->requireUser()->getAllowedStoreIds());
        }
        return $this->assertProductType($product, $requiredType);
    }

    /**
     * Admin tokens, or an API user holding a products grant (write counts so
     * an integration can read back what it writes).
     */
    protected function canReadBackendProducts(): bool
    {
        return $this->hasBackendAccess('products');
    }

    /**
     * Load for a model save: global scope unless the request names a store.
     * Saving a product loaded at a store view clones every store-scope attribute
     * into that store (the _canUpdateAttribute store-view rule), so writes must
     * not inherit the read context's default store. resolveWriteScope() keeps
     * store-restricted tokens off the global scope.
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    protected function loadProductForWrite(int $id, ApiUser $user, ?string $requiredType = null): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        return $this->assertProductType(self::productService()->getById($id, $this->resolveWriteScope($user)), $requiredType);
    }

    /**
     * Return the websites of the store views of a store-restricted user, or null for an unrestricted user.
     *
     * @return int[]|null
     */
    protected function getAllowedWebsiteIds(ApiUser $user): ?array
    {
        return self::productService()->getWebsiteIds($user->getAllowedStoreIds());
    }

    /**
     * @throws \Mage_Core_Exception_Forbidden when the product is in no website of the store views of the user
     */
    protected function assertProductWebsitesAllowed(Mage_Catalog_Model_Product $product, ApiUser $user): void
    {
        self::productService()->assertInStores($product, $user->getAllowedStoreIds());
    }

    private static function productService(): Mage_Catalog_Service_Product
    {
        /** @var Mage_Catalog_Service_Product */
        return Mage::getService('catalog/product');
    }

    /**
     * @throws \Mage_Core_Exception_NoSuchEntity when the product is not of the type $requiredType
     */
    private function assertProductType(Mage_Catalog_Model_Product $product, ?string $requiredType): Mage_Catalog_Model_Product
    {
        if ($requiredType !== null && $product->getTypeId() !== $requiredType) {
            throw new \Mage_Core_Exception_NoSuchEntity("Product is not a {$requiredType} product");
        }
        return $product;
    }
}
