<?php

/**
 * Load, check, save and delete products for the admin product pages, the product API and GraphQL.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;

class Mage_Catalog_Service_Product
{
    private const MAX_ENTITY_ID = 2147483647;

    /**
     * Load the product $id with the values of the store view $storeId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, int $storeId = Mage_Core_Model_App::ADMIN_STORE_ID): Product
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($storeId);
        if ($id > 0 && $id <= self::MAX_ENTITY_ID) {
            $product->load($id);
        }
        if (!$product->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Product not found'));
        }
        return $product;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getBySku(string $sku, int $storeId = Mage_Core_Model_App::ADMIN_STORE_ID): Product
    {
        return $this->getById($this->getIdBySku($sku) ?? 0, $storeId);
    }

    public function getIdBySku(string $sku): ?int
    {
        $id = $sku === '' ? false : Mage::getResourceSingleton('catalog/product')->getIdBySku($sku);
        return $id ? (int) $id : null;
    }

    /**
     * Load the product $id for the storefront of the store view $storeId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store view does not show the product
     */
    public function getVisibleById(int $id, int $storeId): Product
    {
        $product = $this->getById($id, $storeId);
        if (!Mage::helper('catalog/product')->isVisibleInStore($product, $storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Product not found'));
        }
        return $product;
    }

    /**
     * Return a new product of the type $typeId and the attribute set $attributeSetId, or the default attribute set,
     * with the values of the admin scope. save() checks the type and the attribute set.
     */
    public function newProduct(string $typeId = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, ?int $attributeSetId = null): Product
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product');
        return $product->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->setTypeId($typeId)
            ->setAttributeSetId($attributeSetId ?? (int) $product->getDefaultAttributeSetId());
    }

    /**
     * Return the websites of the store views $storeIds, or null when $storeIds is null. A store view that does not
     * exist gives no website.
     *
     * @param list<int>|null $storeIds
     * @return list<int>|null
     */
    public function getWebsiteIds(?array $storeIds): ?array
    {
        if ($storeIds === null) {
            return null;
        }
        $websiteIds = [];
        foreach ($storeIds as $storeId) {
            try {
                $websiteIds[] = (int) Mage::app()->getStore($storeId)->getWebsiteId();
            } catch (Mage_Core_Model_Store_Exception) {
            }
        }
        return array_values(array_unique($websiteIds));
    }

    /**
     * Return the websites of a new product that names none: the website of the store view $storeId, or the first
     * website. With $storeIds, only the websites of these store views.
     *
     * @param list<int>|null $storeIds
     * @return list<int>
     */
    public function getDefaultWebsiteIds(int $storeId, ?array $storeIds = null): array
    {
        $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();
        $defaults = [$websiteId ?: 1];
        $allowed = $this->getWebsiteIds($storeIds);
        if ($allowed === null) {
            return $defaults;
        }
        $scoped = array_values(array_intersect($defaults, $allowed));
        return $scoped !== [] ? $scoped : $allowed;
    }

    /**
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden when the product is in no website of the store views $storeIds
     */
    public function assertInStores(Product $product, ?array $storeIds): void
    {
        $this->assertWebsitesInStores(array_map(intval(...), $product->getWebsiteIds()), $storeIds);
    }

    /**
     * @param list<int> $productIds
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden when a product is in no website of the store views $storeIds
     */
    public function assertIdsInStores(array $productIds, ?array $storeIds): void
    {
        if ($storeIds === null) {
            return;
        }
        $websiteIds = Mage::getResourceSingleton('catalog/product')->getWebsiteIdsByProductIds($productIds);
        foreach ($productIds as $productId) {
            $this->assertWebsitesInStores($websiteIds[$productId] ?? [], $storeIds);
        }
    }

    /**
     * @param array<int|string> $websiteIds the websites of a product
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden when no website of $websiteIds has a store view of $storeIds
     */
    public function assertWebsitesInStores(array $websiteIds, ?array $storeIds): void
    {
        $allowed = $this->getWebsiteIds($storeIds);
        if ($allowed !== null && array_intersect(array_map(intval(...), $websiteIds), $allowed) === []) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__("Access denied for this product's websites"));
        }
    }

    /**
     * Put $product in the websites $websiteIds.
     *
     * @param array<int|string> $websiteIds
     * @param list<int>|null $storeIds with a list, each website must have a store view of the list
     * @throws Mage_Core_Exception_Forbidden
     */
    public function setWebsiteIds(Product $product, array $websiteIds, ?array $storeIds = null): void
    {
        $this->assertWebsitesAllowed($websiteIds, $storeIds);
        $product->setWebsiteIds(array_map(intval(...), $websiteIds));
    }

    /**
     * Add the products $productIds to the websites $addIds and remove them from the websites $removeIds.
     *
     * @param list<int> $productIds
     * @param array<int|string> $addIds
     * @param array<int|string> $removeIds
     * @param list<int>|null $storeIds with a list, each website must have a store view of the list
     * @throws Mage_Core_Exception_Forbidden
     */
    public function changeWebsites(array $productIds, array $addIds, array $removeIds, ?array $storeIds = null): void
    {
        $this->assertWebsitesAllowed([...$addIds, ...$removeIds], $storeIds);
        /** @var Mage_Catalog_Model_Product_Action $action */
        $action = Mage::getSingleton('catalog/product_action');
        if ($removeIds !== []) {
            $action->updateWebsites($productIds, $removeIds, 'remove');
        }
        if ($addIds !== []) {
            $action->updateWebsites($productIds, $addIds, 'add');
        }
        if ($addIds !== [] || $removeIds !== []) {
            Mage::dispatchEvent('catalog_product_to_website_change', ['products' => $productIds]);
        }
    }

    /**
     * Remove the store view value of each attribute in $codes, so that the store view shows the default value.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_Input for the admin scope, an unknown attribute and a global attribute
     */
    public function useDefault(Product $product, array $codes): void
    {
        foreach ($this->getStoreAttributes($codes, (int) $product->getStoreId()) as $attribute) {
            $product->setData($attribute->getAttributeCode(), false);
        }
    }

    /**
     * Delete the values of the store view $storeId of each attribute in $codes for the product $productId, without
     * a save of the product.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_Input for the admin scope, an unknown attribute and a global attribute
     */
    public function deleteStoreValues(int $productId, array $codes, int $storeId): void
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        foreach ($this->getStoreAttributes($codes, $storeId) as $attribute) {
            $adapter->delete($attribute->getBackend()->getTable(), [
                'entity_id = ?' => $productId,
                'attribute_id = ?' => (int) $attribute->getId(),
                'store_id = ?' => $storeId,
            ]);
        }
    }

    /**
     * Check and save $product. The transport puts its input into the product first. A product with stock data saves
     * its stock item through Mage_CatalogInventory_Service_Stock.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another product has the SKU
     */
    public function save(Product $product): Product
    {
        $this->validate($product);
        if (is_array($product->getStockData())) {
            /** @var Mage_CatalogInventory_Service_Stock $stock */
            $stock = Mage::getService('cataloginventory/stock');
            $storedItem = $product->getId()
                ? ($product->getStockItem() ?? Mage::getModel('cataloginventory/stock_item')->loadByProduct($product))
                : null;
            $product->setStockData($stock->prepare(
                $product->getStockData(),
                $storedItem?->getId() ? (float) $storedItem->getQty() : null,
            ));
        }
        $product->save();
        return $product;
    }

    public function delete(Product $product): void
    {
        Mage::dispatchEvent('catalog_controller_product_delete', ['product' => $product]);
        $wasSecure = (bool) Mage::registry('isSecureArea');
        if (!$wasSecure) {
            Mage::register('isSecureArea', true);
        }
        try {
            $product->delete();
        } finally {
            if (!$wasSecure) {
                Mage::unregister('isSecureArea');
            }
        }
    }

    /**
     * Set the status $status of the products $productIds in the store view $storeId.
     *
     * @param list<int|string> $productIds
     * @throws Mage_Core_Exception_Input for an unknown status
     * @throws Mage_Core_Exception when a product to enable has no SKU
     */
    public function updateStatus(array $productIds, int $status, int $storeId): void
    {
        if (!array_key_exists($status, Mage_Catalog_Model_Product_Status::getOptionArray())) {
            throw new Mage_Core_Exception_Input()->addError('status', Mage::helper('catalog')->__('Unknown product status: %s', $status));
        }
        $this->updateAttributes($productIds, ['status' => $status], $storeId);
        Mage::dispatchEvent('catalog_controller_product_mass_status', ['product_ids' => $productIds]);
    }

    /**
     * Write the attribute values $data of the products $productIds in the store view $storeId, without a save of
     * each product. An unknown attribute and a static attribute are skipped. The backend of each attribute checks
     * its value, and a date range cannot start after it ends.
     *
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data the value of each attribute, by attribute code
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception when a product to enable has no SKU
     */
    public function updateAttributes(array $productIds, array $data, int $storeId): void
    {
        if (isset($data['status']) && (int) $data['status'] === Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            && !Mage::getModel('catalog/product')->isProductsHasSku($productIds)
        ) {
            throw new Mage_Core_Exception(Mage::helper('catalog')->__('Some of the processed products have no SKU value defined. Please fill it prior to performing operations on these products.'));
        }

        $values = new \Maho\DataObject();
        foreach ($data as $code => $value) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Product::ENTITY, $code);
            if (!$attribute || !$attribute->getAttributeId() || $attribute->getBackend()->isStatic()) {
                unset($data[$code]);
                continue;
            }
            $values->setData($code, $value);
            try {
                $attribute->getBackend()->validate($values);
            } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
                throw new Mage_Core_Exception_Input()->addError($code, $attribute->getFrontendLabel() . ': ' . $e->getMessage());
            }
        }
        if ($data === []) {
            return;
        }
        $this->assertDateRanges($productIds, $data, $storeId);
        Mage::getSingleton('catalog/product_action')->updateAttributes($productIds, $data, $storeId);
    }

    /**
     * Put the product $productId in the categories $categoryIds only, without a save of the product. A category
     * that does not exist is skipped.
     *
     * @param array<int|string> $categoryIds
     */
    public function setCategoryIds(int $productId, array $categoryIds): void
    {
        $categoryIds = array_map(intval(...), Mage::getResourceSingleton('catalog/category')->verifyIds(array_map(intval(...), $categoryIds)));
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/category_product');

        $existing = array_map(intval(...), $write->fetchCol("SELECT category_id FROM {$table} WHERE product_id = ?", [$productId]));
        foreach (array_diff($categoryIds, $existing) as $categoryId) {
            $write->insertOnDuplicate($table, ['category_id' => $categoryId, 'product_id' => $productId, 'position' => 0]);
        }
        $removed = array_diff($existing, $categoryIds);
        if ($removed !== []) {
            $write->delete($table, ['product_id = ?' => $productId, 'category_id IN (?)' => $removed]);
        }
    }

    /**
     * Check the SKU, the name, the type, the attribute set, then the backend of each attribute and the date ranges.
     *
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict when another product has the SKU
     */
    public function validate(Product $product): void
    {
        $helper = Mage::helper('catalog');
        $errors = new Mage_Core_Exception_Input();
        if (trim((string) $product->getSku()) === '') {
            $errors->addError('sku', $helper->__('SKU is required'));
        }
        if ($product->getName() !== false && trim((string) $product->getName()) === '') {
            $errors->addError('name', $helper->__('Name is required'));
        }
        if (!$product->getId() || $product->dataHasChangedFor('type_id')) {
            if (!array_key_exists((string) $product->getTypeId(), Mage_Catalog_Model_Product_Type::getTypes())) {
                $errors->addError('type_id', $helper->__('Unknown product type: %s', (string) $product->getTypeId()));
            }
        }
        if (!$product->getId() || $product->dataHasChangedFor('attribute_set_id')) {
            $set = Mage::getModel('eav/entity_attribute_set')->load((int) $product->getAttributeSetId());
            if (!$set->getId() || (int) $set->getEntityTypeId() !== (int) $product->getResource()->getTypeId()) {
                $errors->addError('attribute_set_id', $helper->__('Attribute set %s not found', (int) $product->getAttributeSetId()));
            }
        }
        $errors->throwIfErrors();

        try {
            $product->validate();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            throw new Mage_Core_Exception_Input()->addError((string) $e->getAttributeCode(), $e->getMessage());
        }
    }

    /**
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data
     * @throws Mage_Core_Exception_Input
     */
    private function assertDateRanges(array $productIds, array $data, int $storeId): void
    {
        $codes = array_merge(array_keys(Product::DATE_RANGES), array_values(Product::DATE_RANGES));
        if (array_intersect($codes, array_keys($data)) === []) {
            return;
        }
        $resource = Mage::getResourceSingleton('catalog/product');
        foreach ($productIds as $productId) {
            $stored = $resource->getAttributeRawValue((int) $productId, $codes, $storeId);
            Product::assertDateRanges(array_merge(is_array($stored) ? $stored : [], array_intersect_key($data, array_flip($codes))));
        }
    }

    /**
     * @param array<int|string> $websiteIds
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden
     */
    private function assertWebsitesAllowed(array $websiteIds, ?array $storeIds): void
    {
        $allowed = $this->getWebsiteIds($storeIds);
        foreach ($allowed === null ? [] : $websiteIds as $websiteId) {
            if (!in_array((int) $websiteId, $allowed, true)) {
                throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__('Access denied for website: %s', $websiteId));
            }
        }
    }

    /**
     * @param list<string> $codes
     * @return list<Mage_Catalog_Model_Resource_Eav_Attribute>
     * @throws Mage_Core_Exception_Input
     */
    private function getStoreAttributes(array $codes, int $storeId): array
    {
        $helper = Mage::helper('catalog');
        $errors = new Mage_Core_Exception_Input();
        if ($codes !== [] && $storeId === Mage_Core_Model_App::ADMIN_STORE_ID) {
            $errors->addError('use_default', $helper->__('useDefault requires a store context (?store=): global values have no default to revert to.'));
            $errors->throwIfErrors();
        }
        $attributes = [];
        foreach ($codes as $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Product::ENTITY, $code);
            if (!$attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute || !$attribute->getId() || $attribute->getBackend()->isStatic()) {
                $errors->addError('use_default', $helper->__('Unknown attribute in useDefault: %s', $code));
            } elseif ($attribute->isScopeGlobal()) {
                $errors->addError('use_default', $helper->__("Attribute '%s' is global scope and has no store override to revert.", $code));
            } else {
                $attributes[] = $attribute;
            }
        }
        $errors->throwIfErrors();
        return $attributes;
    }
}
