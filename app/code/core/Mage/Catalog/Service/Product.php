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
        $product->load($id);
        if (!$product->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Product not found'));
        }
        return $product;
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
     * Keep in $collection only the products that the storefront of the store view $storeId shows: enabled, visible
     * in the catalog or the search, and in the website of the store. getVisibleById() applies the same rule to one
     * product.
     */
    public function addVisibleFilter(Mage_Catalog_Model_Resource_Product_Collection $collection, int $storeId): void
    {
        $collection->setStoreId($storeId)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInSiteIds())
            ->addStoreFilter($storeId);
    }

    /**
     * Return a new product of the type $typeId and the attribute set $attributeSetId, or the default attribute set,
     * with the values of the admin scope. The product is enabled, visible in the catalog and the search, and has no
     * tax class, until the transport sets other values. The save checks the type and the attribute set.
     */
    public function newProduct(string $typeId = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, ?int $attributeSetId = null): Product
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product');
        return $product->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->setTypeId($typeId)
            ->setAttributeSetId($attributeSetId ?? (int) $product->getDefaultAttributeSetId())
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTaxClassId(0);
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
        // The loaded store views: getStore() queries an unknown ID, and PostgreSQL fails an ID out of the column range
        $stores = Mage::app()->getStores(true);
        $websiteIds = [];
        foreach ($storeIds as $storeId) {
            if (isset($stores[$storeId])) {
                $websiteIds[] = (int) $stores[$storeId]->getWebsiteId();
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
        $allowed = $this->getWebsiteIds($storeIds);
        if ($allowed !== null && !$product->isInWebsites($allowed)) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__("Access denied for this product's websites"));
        }
    }

    /**
     * @param list<int> $productIds
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden when a product is in no website of the store views $storeIds
     */
    public function assertIdsInStores(array $productIds, ?array $storeIds): void
    {
        $allowed = $this->getWebsiteIds($storeIds);
        if ($allowed !== null && Mage::getResourceSingleton('catalog/product')->getIdsNotInWebsites($productIds, $allowed) !== []) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__("Access denied for this product's websites"));
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
     * Put $product in the websites $websiteIds. In single store mode, the product goes in the one website.
     *
     * @param array<int|string> $websiteIds
     * @param list<int>|null $storeIds with a list, each website must have a store view of the list
     * @throws Mage_Core_Exception_Forbidden
     */
    public function setWebsiteIds(Product $product, array $websiteIds, ?array $storeIds = null): void
    {
        $websiteIds = Product::getSingleStoreWebsiteIds() ?? $websiteIds;
        $this->assertWebsitesAllowed($websiteIds, $storeIds);
        $product->setWebsiteIds(array_map(intval(...), $websiteIds));
    }

    /**
     * Add the products $productIds to the websites $addIds and remove them from the websites $removeIds. In single
     * store mode, the products stay in the one website.
     *
     * @param list<int> $productIds
     * @param array<int|string> $addIds
     * @param array<int|string> $removeIds
     * @param list<int>|null $storeIds with a list, each website must have a store view of the list
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception when a product has no SKU
     */
    public function changeWebsites(array $productIds, array $addIds, array $removeIds, ?array $storeIds = null): void
    {
        $this->assertHaveSku($productIds);
        $singleStoreWebsiteIds = Product::getSingleStoreWebsiteIds();
        if ($singleStoreWebsiteIds !== null) {
            $addIds = $addIds === [] && $removeIds === [] ? [] : $singleStoreWebsiteIds;
            $removeIds = [];
        }
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
        foreach (Mage::helper('catalog')->getStoreScopeAttributes(Product::ENTITY, $codes, (int) $product->getStoreId()) as $attribute) {
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
        Mage::getSingleton('catalog/product_action')
            ->deleteStoreValues($productId, Mage::helper('catalog')->getStoreScopeAttributes(Product::ENTITY, $codes, $storeId), $storeId);
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
            $product->setStockData($stock->prepare($product->getStockData()));
        }
        $product->save();
        return $product;
    }

    /**
     * Save $product after a change of one of its parts: its links, its custom options, its prices, its images, its
     * children. The product must have a SKU, as for a mass change. The save does not check the attribute values
     * again: the caller did not send them, so an old value must not refuse the change of a part.
     *
     * @throws Mage_Core_Exception_Input when the product has no SKU
     */
    public function savePart(Product $product): Product
    {
        if (trim((string) $product->getSku()) === '') {
            throw new Mage_Core_Exception_Input()->addError('sku', Mage::helper('catalog')->__('SKU is required'));
        }
        $product->save();
        return $product;
    }

    /**
     * Copy the attribute values of $product between store views. For each pair of $stores, the values of the
     * store view of the value replace the values of the store view of the key. A copy from the default values
     * (store 0) writes nothing: the target store view keeps its own values and uses the default for the others.
     *
     * @param array<int|string, int|string> $stores the ID of the source store view, by the ID of the target store view
     * @throws Mage_Core_Exception_Input
     */
    public function copyToStores(Product $product, array $stores): void
    {
        foreach ($stores as $storeTo => $storeFrom) {
            $productInStore = $this->getById((int) $product->getId(), (int) $storeFrom);
            Mage::dispatchEvent('product_duplicate_attributes', [
                'product' => $productInStore,
                'storeTo' => $storeTo,
                'storeFrom' => $storeFrom,
            ]);
            $this->save($productInStore->setStoreId((int) $storeTo));
        }
    }

    /**
     * Create a copy of $product, with its images or without them. The copy is disabled and has no SKU.
     */
    public function duplicate(Product $product, bool $withImages = true): Product
    {
        return $product->duplicate($withImages);
    }

    public function delete(Product $product): void
    {
        Mage::dispatchEvent('catalog_controller_product_delete', ['product' => $product]);
        Mage::helper('catalog')->deleteInSecureArea($product);
    }

    /**
     * Set the status $status of the products $productIds in the store view $storeId. Only a product with a SKU
     * can become enabled.
     *
     * @param list<int|string> $productIds
     * @throws Mage_Core_Exception_Input for an unknown status
     * @throws Mage_Core_Exception when the status is enabled and a product has no SKU
     */
    public function updateStatus(array $productIds, int $status, int $storeId): void
    {
        if (!array_key_exists($status, Mage_Catalog_Model_Product_Status::getOptionArray())) {
            throw new Mage_Core_Exception_Input()->addError('status', Mage::helper('catalog')->__('Unknown product status: %s', $status));
        }
        if ($status === Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
            $this->assertHaveSku($productIds);
        }
        $this->writeAttributes($productIds, ['status' => $status], $storeId);
        Mage::dispatchEvent('catalog_controller_product_mass_status', ['product_ids' => $productIds]);
    }

    /**
     * Write the attribute values $data of the products $productIds in the store view $storeId, without a save of
     * each product. An unknown attribute and a static attribute are skipped. The backend of each attribute checks
     * its value, and a date range cannot start after it ends. A URL key gets the format of a product save. An empty
     * URL key becomes the name of the product for one product, and is skipped for more than one.
     *
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data the value of each attribute, by attribute code
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception when a product has no SKU
     */
    public function updateAttributes(array $productIds, array $data, int $storeId): void
    {
        $this->assertHaveSku($productIds);
        $this->writeAttributes($productIds, $data, $storeId);
    }

    /**
     * Check the value of each attribute of $data with the backend of the attribute. An unknown attribute and a
     * static attribute are skipped.
     *
     * @param array<string, mixed> $data the value of each attribute, by attribute code
     * @return array<string, mixed> the values of $data that a mass change writes
     * @throws Mage_Core_Exception_Input with the attribute code as the field
     */
    public function validateAttributes(array $data): array
    {
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
        return $data;
    }

    /**
     * Write a change of the product $product without a save of the product: the attribute values $attributes in
     * the store view of the product, then the other parts. A part that is null or empty stays as it is.
     *
     * @param array<string, mixed> $attributes the value of each attribute, by attribute code
     * @param list<string> $useDefault the attributes whose store view value goes back to the default value
     * @param array<string, mixed> $stockData the fields of the stock item
     * @param array<int|string>|null $categoryIds the only categories of the product
     * @param array<int|string>|null $websiteIds the only websites of the product
     * @param list<int>|null $storeIds with a list, each website that changes must have a store view of the list
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception when the product has no SKU
     */
    public function updateWithoutSave(
        Product $product,
        array $attributes,
        array $useDefault = [],
        array $stockData = [],
        ?array $categoryIds = null,
        ?array $websiteIds = null,
        ?array $storeIds = null,
    ): Product {
        $id = (int) $product->getId();
        $storeId = (int) $product->getStoreId();
        $this->updateAttributes([$id], $attributes, $storeId);
        $product->addData($attributes);
        $this->deleteStoreValues($id, $useDefault, $storeId);
        if ($stockData !== []) {
            /** @var Mage_CatalogInventory_Service_Stock $stock */
            $stock = Mage::getService('cataloginventory/stock');
            $stock->update($stock->getByProductId($id), $stockData);
        }
        if ($categoryIds !== null) {
            $this->setCategoryIds($id, $categoryIds);
        }
        if ($websiteIds !== null) {
            $current = array_map(intval(...), $product->getWebsiteIds());
            $wanted = array_map(intval(...), $websiteIds);
            $this->changeWebsites([$id], array_values(array_diff($wanted, $current)), array_values(array_diff($current, $wanted)), $storeIds);
            $product->setWebsiteIds($wanted);
        }
        return $product;
    }

    /**
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data
     * @throws Mage_Core_Exception_Input
     */
    private function writeAttributes(array $productIds, array $data, int $storeId): void
    {
        $data = $this->validateAttributes($this->formatUrlKey($productIds, $data, $storeId));
        if ($data === []) {
            return;
        }
        $this->assertDateRanges($productIds, $data, $storeId);
        Mage::getSingleton('catalog/product_action')->updateAttributes($productIds, $data, $storeId);
    }

    /**
     * Refuse a mass change when one of the products $productIds has no SKU.
     *
     * @param array<int|string> $productIds
     * @throws Mage_Core_Exception
     */
    public function assertHaveSku(array $productIds): void
    {
        if ($productIds !== [] && Mage::getModel('catalog/product')->isProductsHasSku($productIds) === false) {
            throw new Mage_Core_Exception(Mage::helper('catalog')->__('Some of the processed products have no SKU value defined. Please fill it prior to performing operations on these products.'));
        }
    }

    /**
     * Move the products $productIds to the attribute set $setId, without a save of each product. The indexes and
     * the product cache follow.
     *
     * @param array<int|string> $productIds
     * @return int the number of products
     * @throws Mage_Core_Exception_Input when the set is not a product attribute set
     * @throws Mage_Core_Exception_Conflict when the set does not have an attribute that a configurable product uses
     */
    public function changeAttributeSet(array $productIds, int $setId): int
    {
        $helper = Mage::helper('catalog');
        $productIds = array_values(array_unique(array_filter(array_map(intval(...), $productIds))));
        /** @var Mage_Catalog_Model_Resource_Product $resource */
        $resource = Mage::getResourceSingleton('catalog/product');
        if (!Mage::getModel('catalog/product')->isProductAttributeSet($setId)) {
            throw new Mage_Core_Exception_Input()->addError('attribute_set_id', $helper->__('Attribute set %s not found', $setId));
        }
        if ($productIds === []) {
            return 0;
        }

        $adapter = $resource->getReadConnection();
        $setAttributeIds = array_map(intval(...), $adapter->fetchCol(
            $adapter->select()
                ->from($resource->getTable('eav/entity_attribute'), ['attribute_id'])
                ->where('attribute_set_id = ?', $setId),
        ));
        $superAttributes = $adapter->fetchAll(
            $adapter->select()
                ->from(['sa' => $resource->getTable('catalog/product_super_attribute')], ['attribute_id'])
                ->join(['e' => $resource->getTable('catalog/product')], 'e.entity_id = sa.product_id', ['sku'])
                ->where('sa.product_id IN (?)', $productIds)
                ->where('e.attribute_set_id != ?', $setId),
        );
        foreach ($superAttributes as $row) {
            if (!in_array((int) $row['attribute_id'], $setAttributeIds, true)) {
                $attribute = Mage::getSingleton('eav/config')->getAttribute(Product::ENTITY, (int) $row['attribute_id']);
                throw new Mage_Core_Exception_Conflict($helper->__(
                    "The attribute set does not have the attribute '%s' that the configurable product %s uses.",
                    $attribute ? $attribute->getAttributeCode() : $row['attribute_id'],
                    $row['sku'],
                ));
            }
        }

        return Mage::getSingleton('catalog/product_action')->updateAttributeSet($productIds, $setId);
    }

    /**
     * Put the product $productId in the categories $categoryIds only, without a save of the product. A category
     * that does not exist is skipped. The category product index and the URL rewrites of the product follow.
     *
     * @param array<int|string> $categoryIds
     */
    public function setCategoryIds(int $productId, array $categoryIds): void
    {
        $categoryIds = array_map(intval(...), Mage::getResourceSingleton('catalog/category')->verifyIds(array_map(intval(...), $categoryIds)));
        Mage::getSingleton('catalog/product_action')->updateCategories($productId, $categoryIds);
    }

    /**
     * Check the SKU and the name, then the rules of the product model for the name, the type and the attribute
     * set, then the backend of each attribute and the date ranges.
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
        // In a store view, false means that the store view uses the default name
        if ($product->getName() !== false && trim((string) $product->getName()) === '') {
            $errors->addError('name', $helper->__('Name is required'));
        }
        $product->validateRecord($errors)->throwIfErrors();

        try {
            $product->validate();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception_Unique $e) {
            throw new Mage_Core_Exception_Conflict($e->getMessage());
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
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data
     * @return array<string, mixed> $data with the URL key in the format of the URL key backend
     */
    private function formatUrlKey(array $productIds, array $data, int $storeId): array
    {
        if (!array_key_exists('url_key', $data)) {
            return $data;
        }
        $urlKey = (string) $data['url_key'];
        if ($urlKey === '' && count($productIds) === 1) {
            $urlKey = (string) ($data['name']
                ?? Mage::getResourceSingleton('catalog/product')->getAttributeRawValue((int) reset($productIds), 'name', $storeId));
        }
        if ($urlKey === '') {
            unset($data['url_key']);
            return $data;
        }
        $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
        $data['url_key'] = Mage::getSingleton('catalog/product_url')->formatUrlKey($urlKey, $locale);
        return $data;
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
}
