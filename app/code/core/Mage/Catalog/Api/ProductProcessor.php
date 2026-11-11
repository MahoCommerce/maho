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
use Mage_Catalog_Service_Product;
use Mage_CatalogInventory_Service_Stock;
use Mage_Core_Exception_Input;
use Mage_Core_Exception_NoSuchEntity;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\CustomAttributes;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Give a product write to the product service. Requires the products/write or the products/delete permission.
 *
 * A fast update (the X-Fast-Update header or ?fast=true) writes the attribute values without a save of the
 * product, so it skips the observers of a save. A request that changes the SKU or the attribute set always saves
 * the product.
 */
final class ProductProcessor extends \Maho\ApiPlatform\Processor
{
    private const VISIBILITY_MAP = [
        'not_visible' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
        'catalog' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        'search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
        'catalog_search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ];

    /** The attribute that each scalar field of the DTO sets. A null field leaves the attribute as it is. */
    private const SCALAR_ATTRIBUTE_FIELDS = [
        'description' => 'description',
        'shortDescription' => 'short_description',
        'price' => 'price',
        'specialPrice' => 'special_price',
        'weight' => 'weight',
        'urlKey' => 'url_key',
        'metaTitle' => 'meta_title',
        'metaDescription' => 'meta_description',
        'metaKeywords' => 'meta_keyword',
        'barcode' => 'barcode',
        'pageLayout' => 'page_layout',
        'cost' => 'cost',
        'msrp' => 'msrp',
        'msrpEnabled' => 'msrp_enabled',
        'msrpDisplayActualPriceType' => 'msrp_display_actual_price_type',
        'giftMessageAvailable' => 'gift_message_available',
        'optionsContainer' => 'options_container',
        'metaRobots' => 'meta_robots',
        'gtin' => 'gtin',
        'mpn' => 'mpn',
        'countryOfManufacture' => 'country_of_manufacture',
        'customDesign' => 'custom_design',
        'customLayoutUpdate' => 'custom_layout_update',
        'imageLabel' => 'image_label',
        'skuType' => 'sku_type',
        'priceType' => 'price_type',
        'weightType' => 'weight_type',
        'priceView' => 'price_view',
        'shipmentType' => 'shipment_type',
        'linksTitle' => 'links_title',
        'linksPurchasedSeparately' => 'links_purchased_separately',
        'samplesTitle' => 'samples_title',
    ];

    /** The attribute that each date field of the DTO sets. An empty string clears the date. */
    private const DATE_ATTRIBUTE_FIELDS = [
        'specialFromDate' => 'special_from_date',
        'specialToDate' => 'special_to_date',
        'newsFromDate' => 'news_from_date',
        'newsToDate' => 'news_to_date',
        'customDesignFrom' => 'custom_design_from',
        'customDesignTo' => 'custom_design_to',
    ];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        $user = $this->requireUser();
        $service = self::service();

        if ($operation instanceof DeleteOperationInterface) {
            $product = $service->getById((int) $uriVariables['id']);
            $service->assertInStores($product, $user->getAllowedStoreIds());
            $oldData = $product->getData();
            $service->delete($product);
            $this->logApiActivity('catalog/product', 'delete', $oldData, null);
            return null;
        }

        assert($data instanceof Product);
        StoreContext::ensureStore();

        if (!isset($uriVariables['id'])) {
            return $this->create($data, $user);
        }

        $request = $context['request'] ?? null;
        // The DTO default of categoryIds is [], so only the body tells an empty list from an absent field
        $categoryIdsSent = array_key_exists('categoryIds', $this->parseRequestBody($request));
        $fast = $request?->headers->get('X-Fast-Update') === 'true' || $request?->query->get('fast') === 'true';
        // The fast path writes attribute values only, so a change of the SKU or the attribute set takes the full path
        if ($fast && $data->sku === '' && $data->attributeSetId === null) {
            return $this->fastUpdate((int) $uriVariables['id'], $data, $user, $categoryIdsSent);
        }
        return $this->update((int) $uriVariables['id'], $data, $user, $categoryIdsSent);
    }

    private static function service(): Mage_Catalog_Service_Product
    {
        /** @var Mage_Catalog_Service_Product */
        return Mage::getService('catalog/product');
    }

    private static function stockService(): Mage_CatalogInventory_Service_Stock
    {
        /** @var Mage_CatalogInventory_Service_Stock */
        return Mage::getService('cataloginventory/stock');
    }

    private function create(Product $data, ApiUser $user): Product
    {
        $service = self::service();
        $product = $service->newProduct($data->type ?: Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, $data->attributeSetId ?: null);
        $product->setSku($data->sku)
            ->setName($data->name)
            ->setStatus(($data->isActive ?? true) ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
            ->setVisibility($this->toVisibility($data->visibility) ?? Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTaxClassId($data->taxClassId ?? 0);
        $product->addData($this->collectAttributeData($data));
        $product->addData($this->collectCustomAttributeData($product, $data));
        $allowedStoreIds = $user->getAllowedStoreIds();
        $service->setWebsiteIds($product, $data->websiteIds ?? $service->getDefaultWebsiteIds(StoreContext::getStoreId(), $allowedStoreIds), $allowedStoreIds);
        if ($data->categoryIds !== []) {
            $product->setCategoryIds(array_map(intval(...), $data->categoryIds));
        }
        $this->applyStockData($product, $data);
        $service->save($product);
        $this->logApiActivity('catalog/product', 'create', null, $product);

        return $this->refreshDto($product);
    }

    private function update(int $id, Product $data, ApiUser $user, bool $categoryIdsSent): Product
    {
        $service = self::service();
        $product = $service->getById($id, $this->resolveWriteScope($user));
        $service->assertInStores($product, $user->getAllowedStoreIds());
        $oldData = $product->getData();

        if ($data->name !== '') {
            $product->setName($data->name);
        }
        if ($data->sku !== '') {
            $product->setSku($data->sku);
        }
        if ($data->isActive !== null) {
            $product->setStatus($data->isActive ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
        }
        if ($data->visibility !== null) {
            $product->setVisibility($this->toVisibility($data->visibility));
        }
        if ($data->attributeSetId !== null) {
            $product->setAttributeSetId($data->attributeSetId);
        }
        if ($data->taxClassId !== null) {
            $product->setTaxClassId($data->taxClassId);
        }
        $product->addData($this->collectAttributeData($data));
        $product->addData($this->collectCustomAttributeData($product, $data));
        $service->useDefault($product, array_map(strval(...), $data->useDefault ?? []));
        if ($data->websiteIds !== null) {
            $service->setWebsiteIds($product, $data->websiteIds, $user->getAllowedStoreIds());
        }
        if ($categoryIdsSent) {
            $product->setCategoryIds(array_map(intval(...), $data->categoryIds));
        }
        $this->applyStockData($product, $data);

        // A store view write stores only the fields of the request.
        $inherited = StoreScopeWrite::keepInheritedValues(
            $product,
            $oldData,
            StoreScopeWrite::requestedCodes($data, $data->isActive !== null ? ['status'] : []),
        );
        $service->save($product);
        StoreScopeWrite::restoreInheritedValues($product, $oldData, $inherited);
        $this->logApiActivity('catalog/product', 'update', $oldData, $product);

        return $this->refreshDto($product);
    }

    private function fastUpdate(int $id, Product $data, ApiUser $user, bool $categoryIdsSent): Product
    {
        $service = self::service();
        $storeId = $this->resolveWriteScope($user);
        $product = $service->getById($id, $storeId);
        $service->assertInStores($product, $user->getAllowedStoreIds());
        $oldData = $product->getData();

        $attrData = $this->collectAttributeData($data) + $this->collectCustomAttributeData($product, $data);
        if ($data->name !== '') {
            $attrData['name'] = $data->name;
        }
        if ($data->visibility !== null) {
            $attrData['visibility'] = $this->toVisibility($data->visibility);
        }
        if ($data->isActive !== null) {
            $attrData['status'] = $data->isActive ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        }
        if ($data->taxClassId !== null) {
            $attrData['tax_class_id'] = $data->taxClassId;
        }
        $service->updateAttributes([$id], $attrData, $storeId);
        // The activity log records the new values
        $product->addData($attrData);

        $service->deleteStoreValues($id, array_map(strval(...), $data->useDefault ?? []), $storeId);
        $stockData = $this->toStockData($data);
        if ($stockData !== []) {
            $stock = self::stockService();
            $stock->update($stock->getByProductId($id), $stockData);
        }
        if ($categoryIdsSent) {
            $service->setCategoryIds($id, $data->categoryIds);
        }
        if ($data->websiteIds !== null) {
            $current = array_map(intval(...), $product->getWebsiteIds());
            $wanted = array_map(intval(...), $data->websiteIds);
            $service->changeWebsites([$id], array_values(array_diff($wanted, $current)), array_values(array_diff($current, $wanted)), $user->getAllowedStoreIds());
            $product->setWebsiteIds($wanted);
        }

        $this->logApiActivity('catalog/product', 'update', $oldData, $product);

        return $this->refreshDto($product);
    }

    /**
     * Return the attribute values that the dedicated fields of the request set, by code: a date in the midnight form
     * of the admin, a flag as 0 or 1.
     *
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input for an invalid date
     */
    private function collectAttributeData(Product $data): array
    {
        $attrData = [];
        foreach (self::SCALAR_ATTRIBUTE_FIELDS as $property => $code) {
            if ($data->$property !== null) {
                $attrData[$code] = is_bool($data->$property) ? (int) $data->$property : $data->$property;
            }
        }
        foreach (self::DATE_ATTRIBUTE_FIELDS as $property => $code) {
            if ($data->$property !== null) {
                $attrData[$code] = $this->toDate($data->$property, $code);
            }
        }
        // An attribute that this install does not have, such as gtin on an old install, is skipped
        foreach (array_keys($attrData) as $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                unset($attrData[$code]);
            }
        }
        return $attrData;
    }

    /**
     * Return the values of the customAttributes map, by code: the user-defined attributes without a dedicated field.
     * A user-defined attribute must be in the set of $product, because the nightly eav_clean_orphaned_records cron
     * deletes a value whose attribute is not in the set.
     *
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    private function collectCustomAttributeData(Mage_Catalog_Model_Product $product, Product $data): array
    {
        if (empty($data->customAttributes)) {
            return [];
        }
        $values = new \Maho\DataObject();
        CustomAttributes::apply(
            $values,
            $data->customAttributes,
            Product::customAttributeDefinitions(),
            array_keys(Product::customAttributeDefinitions($product)),
        );
        return $values->getData();
    }

    /**
     * Return the stock change of the request: the stockData map, in snake case or camel case, and the stockQty
     * shortcut.
     *
     * @return array<string, float|int>
     */
    private function toStockData(Product $data): array
    {
        $stockData = self::stockService()->toColumns($data->stockData ?? []);
        if (!isset($stockData['qty']) && $data->stockQty !== null) {
            $stockData['qty'] = $data->stockQty;
        }
        return $stockData;
    }

    private function applyStockData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        $stockData = $this->toStockData($data);
        if ($stockData !== []) {
            $product->setStockData($stockData);
        }
    }

    /**
     * @throws Mage_Core_Exception_Input for an unknown visibility
     */
    private function toVisibility(?string $visibility): ?int
    {
        if ($visibility === null) {
            return null;
        }
        return self::VISIBILITY_MAP[$visibility] ?? throw new Mage_Core_Exception_Input()->addError(
            'visibility',
            'visibility must be one of: ' . implode(', ', array_keys(self::VISIBILITY_MAP)),
        );
    }

    /**
     * Return the date $value in the midnight 'Y-m-d H:i:s' form of the admin. An empty string clears the date.
     *
     * @throws Mage_Core_Exception_Input
     */
    private function toDate(string $value, string $code): ?string
    {
        $field = lcfirst(str_replace('_', '', ucwords($code, '_')));
        // formatDateForDb() reads a numeric string as a unix timestamp, so "20260801" would store 1970-08-22
        try {
            $date = is_numeric($value) ? false : Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Exception) {
            $date = false;
        }
        if ($date === false) {
            throw new Mage_Core_Exception_Input()->addError($code, "Invalid date for {$field}; use Y-m-d format.");
        }
        return $date === null ? null : $date . ' 00:00:00';
    }

    /**
     * Return the product as a GET of the same caller shows it, with the values that a fast update writes directly.
     */
    private function refreshDto(Mage_Catalog_Model_Product $product): Product
    {
        $productId = (int) $product->getId();
        // The inventory observer keeps the stock item of each loaded product for the request
        $product->clearInstance();

        return new ProductProvider($this->security)->loadProductDto($productId, visibleOnly: false)
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Product not found'));
    }
}
