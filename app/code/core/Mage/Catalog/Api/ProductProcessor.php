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
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Status;
use Mage_Catalog_Model_Product_Type;
use Mage_Catalog_Model_Product_Visibility;
use Mage_CatalogInventory_Model_Stock_Item;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\ActivityLogTrait;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Maho\ApiPlatform\Trait\StockWriterTrait;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Product State Processor.
 *
 * Handles create, update, and delete operations for products.
 * Requires JWT authentication with products/write or products/delete permission.
 *
 * Supports a fast-update mode (via X-Fast-Update header or ?fast=true query param)
 * that uses bulk EAV updateAttributes() and direct SQL for stock/categories,
 * bypassing model save for significantly faster updates.
 */
final class ProductProcessor extends \Maho\ApiPlatform\Processor
{
    use ActivityLogTrait;
    use ProductLoaderTrait;
    use StockWriterTrait;

    private const VISIBILITY_MAP = [
        'not_visible' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
        'catalog' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        'search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
        'catalog_search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'products/write');

        assert($data instanceof Product);

        if (isset($uriVariables['id'])) {
            $fastMode = ($context['request'] ?? null)?->headers->get('X-Fast-Update') === 'true'
                || ($context['request'] ?? null)?->query->get('fast') === 'true';

            if ($fastMode) {
                return $this->handleFastUpdate((int) $uriVariables['id'], $data, $user);
            }
            return $this->handleUpdate((int) $uriVariables['id'], $data, $user);
        }

        return $this->handleCreate($data, $user);
    }

    private function handleCreate(Product $data, ApiUser $user): Product
    {
        StoreContext::ensureStore();

        if (empty($data->sku)) {
            throw new BadRequestHttpException('SKU is required');
        }

        if (empty($data->name)) {
            throw new BadRequestHttpException('Name is required');
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        $product->setData([
            'sku' => $data->sku,
            'name' => $data->name,
            'type_id' => $data->type ?: Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'attribute_set_id' => $data->attributeSetId ?: (int) Mage::getModel('catalog/product')->getDefaultAttributeSetId(),
            'status' => ($data->isActive ?? true)
                ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                : Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
            'visibility' => $data->visibility !== null
                ? (self::VISIBILITY_MAP[$data->visibility] ?? Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                : Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'tax_class_id' => $data->taxClassId ?? 0,
        ]);

        $this->applyProductData($product, $data);

        $websiteIds = $data->websiteIds ?? $this->getDefaultWebsiteIds($user);
        $this->validateSubmittedWebsiteIds($websiteIds, $user);
        $product->setWebsiteIds($websiteIds);

        $this->safeSave($product, 'create product');

        if (!empty($data->categoryIds)) {
            $this->assignCategories($product, $data->categoryIds);
        }

        $this->updateStockData($product, $data);
        $this->invalidateCache((int) $product->getId());
        $this->logApiActivity('catalog/product', 'create', null, $product, $user);

        return $this->refreshDto($product, $data);
    }

    private function handleUpdate(int $id, Product $data, ApiUser $user): Product
    {
        StoreContext::ensureStore();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        $storeId = StoreContext::getStoreId();
        if ($storeId) {
            $product->setStoreId($storeId);
        }

        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $this->authorizeProductWebsites($product, $user);

        $oldData = $product->getData();

        if ($data->name !== '') {
            $product->setName($data->name);
        }
        if ($data->sku !== '') {
            $product->setSku($data->sku);
        }

        if ($data->isActive !== null) {
            $product->setStatus(
                $data->isActive
                    ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                    : Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
            );
        }

        if ($data->visibility !== null) {
            $product->setVisibility(
                self::VISIBILITY_MAP[$data->visibility] ?? (int) $oldData['visibility'],
            );
        }

        if ($data->attributeSetId !== null) {
            $product->setAttributeSetId($data->attributeSetId);
        }
        if ($data->taxClassId !== null) {
            $product->setTaxClassId($data->taxClassId);
        }

        $this->applyProductData($product, $data);

        if ($data->websiteIds !== null) {
            $this->validateSubmittedWebsiteIds($data->websiteIds, $user);
            $product->setWebsiteIds($data->websiteIds);
        }

        $this->safeSave($product, 'update product');

        if ($data->categoryIds !== []) {
            $this->assignCategories($product, $data->categoryIds);
        }

        $this->updateStockData($product, $data);
        $this->invalidateCache((int) $product->getId());
        $this->logApiActivity('catalog/product', 'update', $oldData, $product, $user);

        return $this->refreshDto($product, $data);
    }

    /**
     * Fast update using bulk EAV updateAttributes() and direct SQL.
     *
     * Bypasses model save, observers, and URL rewrites for significantly faster updates.
     * Modeled on DataSync's _updateProductFast() pattern.
     */
    private function handleFastUpdate(int $id, Product $data, ApiUser $user): Product
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

        $this->authorizeProductWebsites($product, $user);

        // A store-restricted user may only write attribute values into a store
        // they're allowed to (storeId 0 = admin/default scope = all stores).
        if ($storeId && $user->getAllowedStoreIds() !== null && !$user->canAccessStore($storeId)) {
            throw new AccessDeniedHttpException("Access denied for store: {$storeId}");
        }

        $oldData = $product->getData();

        // Build attribute data array (only non-null fields from the DTO)
        $attrData = [];
        if ($data->name !== '') {
            $attrData['name'] = $data->name;
        }
        if ($data->description !== null) {
            $attrData['description'] = $data->description;
        }
        if ($data->shortDescription !== null) {
            $attrData['short_description'] = $data->shortDescription;
        }
        if ($data->price !== null) {
            $attrData['price'] = $data->price;
        }
        if ($data->specialPrice !== null) {
            $attrData['special_price'] = $data->specialPrice;
        }
        if ($data->weight !== null) {
            $attrData['weight'] = $data->weight;
        }
        if ($data->urlKey !== null) {
            $attrData['url_key'] = $data->urlKey;
        }
        if ($data->metaTitle !== null) {
            $attrData['meta_title'] = $data->metaTitle;
        }
        if ($data->metaDescription !== null) {
            $attrData['meta_description'] = $data->metaDescription;
        }
        if ($data->metaKeywords !== null) {
            $attrData['meta_keyword'] = $data->metaKeywords;
        }
        if ($data->visibility !== null) {
            $attrData['visibility'] = self::VISIBILITY_MAP[$data->visibility] ?? (int) $oldData['visibility'];
        }
        if ($data->isActive !== null) {
            $attrData['status'] = $data->isActive
                ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                : Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        }

        if ($data->barcode !== null) {
            $attrData['barcode'] = $data->barcode;
        }
        if ($data->pageLayout !== null) {
            $attrData['page_layout'] = $data->pageLayout;
        }

        // Filter to only non-static EAV attributes (updateAttributes only works with EAV value tables)
        $attributes = $product->getAttributes();
        $validAttrCodes = [];
        foreach ($attributes as $attrCode => $attribute) {
            $backendType = $attribute->getBackendType();
            if ($backendType && $backendType !== 'static') {
                $validAttrCodes[] = $attrCode;
            }
        }
        $attrData = array_filter($attrData, fn($key) => in_array($key, $validAttrCodes), ARRAY_FILTER_USE_KEY);

        // Bulk EAV update, bypasses model save, observers, URL rewrites
        if (!empty($attrData)) {
            try {
                Mage::getSingleton('catalog/product_action')
                    ->updateAttributes([$id], $attrData, $storeId ?: 0);
            } catch (\Throwable $e) {
                throw new UnprocessableEntityHttpException('Failed to update product: ' . $e->getMessage());
            }
        }

        // Direct SQL for stock
        if ($data->stockQty !== null || $data->stockData !== null) {
            $this->updateStockDirect($id, $data);
        }

        // Direct SQL for categories
        if ($data->categoryIds !== []) {
            $this->updateCategoriesDirect($id, $data->categoryIds);
        }

        // Website IDs still use model (infrequent, complex)
        if ($data->websiteIds !== null) {
            $this->validateSubmittedWebsiteIds($data->websiteIds, $user);
            $product->setWebsiteIds($data->websiteIds);
            $product->save();
        }

        $this->invalidateCache($id);
        $this->logApiActivity('catalog/product', 'update', $oldData, $product, $user);

        return $this->refreshDto($product, $data);
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $this->authorizeProductWebsites($product, $user);

        $oldData = $product->getData();

        $this->secureAreaDelete($product, 'delete product');

        $this->invalidateCache($id);
        $this->logApiActivity('catalog/product', 'delete', $oldData, null, $user);

        return null;
    }

    private function applyProductData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        if ($data->description !== null) {
            $product->setDescription($data->description);
        }
        if ($data->shortDescription !== null) {
            $product->setShortDescription($data->shortDescription);
        }
        if ($data->price !== null) {
            $product->setPrice($data->price);
        }
        if ($data->specialPrice !== null) {
            $product->setSpecialPrice($data->specialPrice);
        }
        if ($data->weight !== null) {
            $product->setWeight($data->weight);
        }
        if ($data->urlKey !== null) {
            $product->setUrlKey($data->urlKey);
        }
        if ($data->metaTitle !== null) {
            $product->setMetaTitle($data->metaTitle);
        }
        if ($data->metaDescription !== null) {
            $product->setMetaDescription($data->metaDescription);
        }
        if ($data->metaKeywords !== null) {
            $product->setData('meta_keyword', $data->metaKeywords);
        }
        if ($data->barcode !== null) {
            $product->setData('barcode', $data->barcode);
        }
        if ($data->pageLayout !== null) {
            $product->setData('page_layout', $data->pageLayout);
        }
        if (!empty($data->customAttributesWrite)) {
            $this->applyCustomAttributes($product, $data->customAttributesWrite);
        }
    }

    /**
     * Codes handled by dedicated DTO fields (or otherwise protected). They must
     * never be written through the generic customAttributesWrite bag.
     */
    private const PROTECTED_ATTRIBUTE_CODES = [
        'entity_id', 'type_id', 'sku', 'attribute_set_id', 'tax_class_id',
        'website_ids', 'stock_data', 'status', 'visibility',
        'created_at', 'updated_at', 'entity_type_id',
    ];

    /**
     * Apply arbitrary EAV attribute values supplied via customAttributesWrite.
     *
     * Protected/system codes are rejected outright; unknown codes (not real
     * catalog_product EAV attributes) are skipped silently so a typo can't
     * inject an arbitrary column.
     *
     * @param array<string, mixed> $attributes
     */
    private function applyCustomAttributes(Mage_Catalog_Model_Product $product, array $attributes): void
    {
        $eavConfig = Mage::getSingleton('eav/config');

        foreach ($attributes as $code => $value) {
            $code = (string) $code;

            if (in_array($code, self::PROTECTED_ATTRIBUTE_CODES, true)) {
                throw new BadRequestHttpException(
                    "Attribute '{$code}' cannot be set via customAttributes; use the dedicated field.",
                );
            }

            $attribute = $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                // Unknown attribute, skip silently.
                continue;
            }

            $product->setData($code, $value);
        }
    }

    private function assignCategories(Mage_Catalog_Model_Product $product, array $categoryIds): void
    {
        $categoryIds = array_map('intval', $categoryIds);
        $product->setCategoryIds($categoryIds);
        $this->safeSave($product, 'assign categories');
    }

    /**
     * Resolve the stock qty / availability / manage-stock the caller supplied,
     * coalescing the structured stockData map and the flat stockQty shortcut.
     * Returns null when the request carries no stock change to apply (matching
     * the original early-return: only manage_stock with no qty/availability is
     * treated as "nothing to do").
     *
     * @return array{qty: ?float, isInStock: ?bool, manageStock: ?bool}|null
     */
    private function extractStockInput(Product $data): ?array
    {
        $qty = null;
        $isInStock = null;
        $manageStock = null;

        if ($data->stockData !== null) {
            $qty = isset($data->stockData['qty']) ? (float) $data->stockData['qty'] : null;
            $isInStock = isset($data->stockData['is_in_stock']) ? (bool) $data->stockData['is_in_stock'] : null;
            $manageStock = isset($data->stockData['manage_stock']) ? (bool) $data->stockData['manage_stock'] : null;
        }

        if ($qty === null && $data->stockQty !== null) {
            $qty = $data->stockQty;
        }

        if ($qty === null && $isInStock === null) {
            return null;
        }

        return ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock];
    }

    private function updateStockData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        $input = $this->extractStockInput($data);
        if ($input === null) {
            return;
        }
        ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock] = $input;

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        $isNew = !$stockItem->getId();
        if ($isNew) {
            $stockItem->setProductId($product->getId());
            $stockItem->setStockId(1);
        }

        if ($qty !== null) {
            $this->validateStockQty($qty);
            $stockItem->setQty($qty);
            $isInStock ??= $qty > 0;
        }

        if ($isInStock !== null) {
            $stockItem->setIsInStock($isInStock ? 1 : 0);
        }

        // Only touch manage_stock when the caller explicitly provides it; otherwise
        // preserve the existing setting and default to enabled only for new items.
        if ($manageStock !== null) {
            $stockItem->setManageStock($manageStock ? 1 : 0);
        } elseif ($isNew) {
            $stockItem->setManageStock(1);
        }

        $this->safeSave($stockItem, 'update stock');
    }

    /**
     * Direct SQL stock update for fast-update mode.
     */
    private function updateStockDirect(int $productId, Product $data): void
    {
        $input = $this->extractStockInput($data);
        if ($input === null) {
            return;
        }
        ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock] = $input;

        if ($qty !== null) {
            $this->validateStockQty($qty);
        }

        $stockData = $this->buildStockData($qty, $isInStock, $manageStock);
        $this->upsertStockItemRow($productId, $stockData);
    }

    /**
     * Direct SQL category update for fast-update mode.
     */
    private function updateCategoriesDirect(int $productId, array $categoryIds): void
    {
        $categoryIds = array_map('intval', $categoryIds);

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/category_product');

        $existing = $write->fetchCol(
            "SELECT category_id FROM {$table} WHERE product_id = ?",
            [$productId],
        );
        $existing = array_map('intval', $existing);

        $toAdd = array_diff($categoryIds, $existing);
        $toRemove = array_diff($existing, $categoryIds);

        foreach ($toAdd as $catId) {
            $write->insertOnDuplicate($table, [
                'category_id' => $catId,
                'product_id' => $productId,
                'position' => 0,
            ]);
        }

        if (!empty($toRemove)) {
            $write->delete($table, [
                'product_id = ?' => $productId,
                'category_id IN (?)' => $toRemove,
            ]);
        }
    }


    /**
     * Default website assignment when the request omits websiteIds.
     *
     * For an unrestricted user this mirrors core behaviour (current store's
     * website, falling back to website 1). For a store-restricted user we never
     * broaden beyond the websites they're allowed: we scope the default to the
     * allowed websites so a missing websiteIds can't silently assign a product
     * to a website outside the user's scope.
     *
     * @return int[]
     */
    private function getDefaultWebsiteIds(ApiUser $user): array
    {
        $allowedWebsiteIds = $this->getAllowedWebsiteIds($user);

        $websiteId = (int) Mage::app()->getStore()->getWebsiteId();
        $defaults = $websiteId ? [$websiteId] : [1];

        if ($allowedWebsiteIds === null) {
            return $defaults;
        }

        // Keep only defaults inside the allowed set; if none qualify, fall back
        // to the full allowed set so the product lands in the user's scope.
        $scoped = array_values(array_intersect($defaults, $allowedWebsiteIds));
        return $scoped !== [] ? $scoped : $allowedWebsiteIds;
    }

    /**
     * Reject submitted website IDs that fall outside a store-restricted user's
     * allowed websites. No-op for unrestricted users.
     *
     * @param int[] $websiteIds
     */
    private function validateSubmittedWebsiteIds(array $websiteIds, ApiUser $user): void
    {
        $allowedWebsiteIds = $this->getAllowedWebsiteIds($user);
        if ($allowedWebsiteIds === null) {
            return;
        }

        foreach ($websiteIds as $websiteId) {
            if (!in_array((int) $websiteId, $allowedWebsiteIds, true)) {
                throw new AccessDeniedHttpException("Access denied for website: {$websiteId}");
            }
        }
    }

    private function invalidateCache(int $productId): void
    {
        Mage::app()->cleanCache(["API_PRODUCT_{$productId}", 'API_PRODUCTS']);
    }

    private function refreshDto(Mage_Catalog_Model_Product $product, Product $data): Product
    {
        $data->id = (int) $product->getId();
        $data->status = $product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            ? 'enabled' : 'disabled';
        // Reflect the persisted state back: the input fields are nullable (a
        // partial update may omit them), so the response must echo the product,
        // not whatever the client did or didn't send.
        $data->isActive = $data->status === 'enabled';
        $data->visibility = array_search((int) $product->getVisibility(), self::VISIBILITY_MAP, true) ?: 'catalog_search';
        $data->attributeSetId = $product->getAttributeSetId() !== null ? (int) $product->getAttributeSetId() : null;
        $data->taxClassId = $product->getTaxClassId() !== null ? (int) $product->getTaxClassId() : null;
        $data->createdAt = $product->getCreatedAt();
        $data->updatedAt = $product->getUpdatedAt();
        return $data;
    }

}
