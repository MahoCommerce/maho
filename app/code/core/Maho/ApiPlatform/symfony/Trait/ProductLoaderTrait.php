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
use Maho\ApiPlatform\Service\StoreContext;
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
}
