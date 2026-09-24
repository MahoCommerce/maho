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
use Mage_Core_Model_App;
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

    /**
     * Dedicated scalar DTO fields applied verbatim, property => attribute code.
     * Wired into both write paths (model save and fast EAV update); null means
     * "leave unchanged".
     */
    private const SCALAR_ATTRIBUTE_FIELDS = [
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
        'smallImageLabel' => 'small_image_label',
        'thumbnailLabel' => 'thumbnail_label',
        'skuType' => 'sku_type',
        'priceType' => 'price_type',
        'weightType' => 'weight_type',
        'priceView' => 'price_view',
        'shipmentType' => 'shipment_type',
        'linksTitle' => 'links_title',
        'linksPurchasedSeparately' => 'links_purchased_separately',
        'samplesTitle' => 'samples_title',
    ];

    /** Dedicated date DTO fields (special_from_date semantics), property => attribute code. */
    private const DATE_ATTRIBUTE_FIELDS = [
        'newsFromDate' => 'news_from_date',
        'newsToDate' => 'news_to_date',
        'customDesignFrom' => 'custom_design_from',
        'customDesignTo' => 'custom_design_to',
    ];

    /** These attributes ship with the catalog data upgrades but may be absent on older installs; skip silently then. */
    private const OPTIONAL_ATTRIBUTE_CODES = ['gtin', 'mpn'];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        $user = $this->requireUser();

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        assert($data instanceof Product);

        if (isset($uriVariables['id'])) {
            $fastMode = ($context['request'] ?? null)?->headers->get('X-Fast-Update') === 'true'
                || ($context['request'] ?? null)?->query->get('fast') === 'true';

            // The DTO default of categoryIds is [], so only the body tells an empty list from an absent field
            $categoryIdsSent = array_key_exists('categoryIds', $this->parseRequestBody($context['request'] ?? null));

            if ($fastMode) {
                return $this->handleFastUpdate((int) $uriVariables['id'], $data, $user, $categoryIdsSent);
            }
            return $this->handleUpdate((int) $uriVariables['id'], $data, $user, $categoryIdsSent);
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

        // Creates always write the global (admin) scope so every store view starts
        // from the same values; store overrides come from an update with ?store=.
        // Set after setData(), which replaced the whole data array.
        $product->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);

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

        return $this->refreshDto($product);
    }

    private function handleUpdate(int $id, Product $data, ApiUser $user, bool $categoryIdsSent): Product
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

        $this->assertProductWebsitesAllowed($product, $user);

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
        $this->applyUseDefault($product, $data, $storeId);

        if ($data->websiteIds !== null) {
            $this->validateSubmittedWebsiteIds($data->websiteIds, $user);
            $product->setWebsiteIds($data->websiteIds);
        }

        // A store view write stores only the fields of the request.
        $inherited = StoreScopeWrite::keepInheritedValues(
            $product,
            $oldData,
            StoreScopeWrite::requestedCodes($data, $data->isActive !== null ? ['status'] : []),
        );

        $this->safeSave($product, 'update product');

        if ($categoryIdsSent) {
            $this->assignCategories($product, $data->categoryIds);
        }

        $this->updateStockData($product, $data);
        StoreScopeWrite::restoreInheritedValues($product, $oldData, $inherited);
        $this->invalidateCache((int) $product->getId());
        $this->logApiActivity('catalog/product', 'update', $oldData, $product, $user);

        return $this->refreshDto($product);
    }

    /**
     * Fast update using bulk EAV updateAttributes() and direct SQL.
     *
     * Bypasses model save, observers, and URL rewrites for significantly faster updates.
     * Modeled on DataSync's _updateProductFast() pattern.
     */
    private function handleFastUpdate(int $id, Product $data, ApiUser $user, bool $categoryIdsSent): Product
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

        $this->assertProductWebsitesAllowed($product, $user);

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
        if ($data->specialFromDate !== null) {
            $attrData['special_from_date'] = $this->normalizeDateInput($data->specialFromDate, 'specialFromDate');
        }
        if ($data->specialToDate !== null) {
            $attrData['special_to_date'] = $this->normalizeDateInput($data->specialToDate, 'specialToDate');
        }
        $attrData = array_merge($attrData, $this->collectAttributeData($data));
        $effective = fn(string $code): mixed => array_key_exists($code, $attrData) ? $attrData[$code] : ($oldData[$code] ?? null);
        $this->validateDateRange($effective('special_from_date'), $effective('special_to_date'), 'specialFromDate', 'specialToDate');
        $this->validateDateRange($effective('news_from_date'), $effective('news_to_date'), 'newsFromDate', 'newsToDate');
        $this->validateDateRange($effective('custom_design_from'), $effective('custom_design_to'), 'customDesignFrom', 'customDesignTo');
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
                    ->updateAttributes([$id], $attrData, $storeId);
            } catch (\Throwable $e) {
                throw new UnprocessableEntityHttpException('Failed to update product: ' . $e->getMessage());
            }
            // Reflect the written values so the activity log records the new
            // state, not the pre-update snapshot.
            $product->addData($attrData);
        }

        if (!empty($data->useDefault)) {
            $this->deleteStoreValuesDirect($id, $data, $storeId);
        }

        // Direct SQL for stock
        if ($data->stockQty !== null || $data->stockData !== null) {
            $this->updateStockDirect($id, $data);
        }

        // Direct SQL for categories
        if ($categoryIdsSent) {
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

        return $this->refreshDto($product);
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $this->assertProductWebsitesAllowed($product, $user);

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
        if ($data->specialFromDate !== null) {
            $product->setData('special_from_date', $this->normalizeDateInput($data->specialFromDate, 'specialFromDate'));
        }
        if ($data->specialToDate !== null) {
            $product->setData('special_to_date', $this->normalizeDateInput($data->specialToDate, 'specialToDate'));
        }
        foreach ($this->collectAttributeData($data) as $attrCode => $value) {
            if (in_array($attrCode, self::OPTIONAL_ATTRIBUTE_CODES, true) && !$this->attributeExists($attrCode)) {
                continue;
            }
            $product->setData($attrCode, $value);
        }
        $this->validateDateRange($product->getData('special_from_date'), $product->getData('special_to_date'), 'specialFromDate', 'specialToDate');
        $this->validateDateRange($product->getData('news_from_date'), $product->getData('news_to_date'), 'newsFromDate', 'newsToDate');
        $this->validateDateRange($product->getData('custom_design_from'), $product->getData('custom_design_to'), 'customDesignFrom', 'customDesignTo');
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
     * Revert store overrides to the default value. Catalog EAV treats `false` as
     * the "use default" sentinel: saving it at a store scope deletes that store's
     * value row, so reads fall back to the global value again.
     */
    private function applyUseDefault(Mage_Catalog_Model_Product $product, Product $data, int $storeId): void
    {
        foreach ($this->resolveUseDefaultAttributes($data, $storeId) as $attribute) {
            $product->setData($attribute->getAttributeCode(), false);
        }
    }

    /**
     * Fast-update variant of applyUseDefault(): deletes the store value rows
     * directly, matching the path's model-save bypass.
     */
    private function deleteStoreValuesDirect(int $id, Product $data, int $storeId): void
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        foreach ($this->resolveUseDefaultAttributes($data, $storeId) as $attribute) {
            $adapter->delete($attribute->getBackend()->getTable(), [
                'entity_id = ?' => $id,
                'attribute_id = ?' => (int) $attribute->getId(),
                'store_id = ?' => $storeId,
            ]);
        }
    }

    /**
     * Validate the useDefault input and resolve it to attribute models: requires
     * an explicit store scope, and only non-global attributes can have a store
     * override to revert.
     *
     * @return list<\Mage_Catalog_Model_Resource_Eav_Attribute>
     */
    private function resolveUseDefaultAttributes(Product $data, int $storeId): array
    {
        if (empty($data->useDefault)) {
            return [];
        }

        if ($storeId === Mage_Core_Model_App::ADMIN_STORE_ID) {
            throw new BadRequestHttpException('useDefault requires a store context (?store=): global values have no default to revert to.');
        }

        $attributes = [];
        foreach ($data->useDefault as $code) {
            $code = (string) $code;
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute instanceof \Mage_Catalog_Model_Resource_Eav_Attribute || !$attribute->getId() || $attribute->getBackend()->isStatic()) {
                throw new BadRequestHttpException("Unknown attribute in useDefault: {$code}");
            }
            if ($attribute->isScopeGlobal()) {
                throw new BadRequestHttpException("Attribute '{$code}' is global scope and has no store override to revert.");
            }
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Collect the dedicated attribute fields the request provided, as
     * attribute_code => value: dates normalized to midnight datetimes, bools
     * cast to int. Shared by the model-save and fast-update write paths.
     *
     * @return array<string, mixed>
     */
    private function collectAttributeData(Product $data): array
    {
        $attrData = [];
        foreach (self::SCALAR_ATTRIBUTE_FIELDS as $prop => $attrCode) {
            if ($data->$prop !== null) {
                $attrData[$attrCode] = is_bool($data->$prop) ? (int) $data->$prop : $data->$prop;
            }
        }
        foreach (self::DATE_ATTRIBUTE_FIELDS as $prop => $attrCode) {
            if ($data->$prop !== null) {
                $attrData[$attrCode] = $this->normalizeDateInput($data->$prop, $prop);
            }
        }
        if (isset($attrData['custom_layout_update'])) {
            $this->validateLayoutUpdateXml((string) $attrData['custom_layout_update']);
        }
        return $attrData;
    }

    /**
     * Reject layout-update XML the admin form would reject (disallowed blocks,
     * template overrides, helper attributes). The fast-update path writes EAV
     * values directly, bypassing the attribute backend model that guards the
     * model-save path, so both paths validate here.
     */
    private function validateLayoutUpdateXml(string $xml): void
    {
        $xml = trim($xml);
        if ($xml === '') {
            return;
        }

        /** @var \Mage_Adminhtml_Model_LayoutUpdate_Validator $validator */
        $validator = Mage::getModel('adminhtml/layoutUpdate_validator');
        try {
            $isValid = $validator->isValid($xml);
            $messages = $validator->getMessages();
        } catch (\Throwable) {
            $isValid = false;
            $messages = [];
        }

        if (!$isValid) {
            $message = $messages === [] ? 'XML data is invalid.' : (string) reset($messages);
            throw new BadRequestHttpException("Invalid customLayoutUpdate: {$message}");
        }
    }

    private function attributeExists(string $code): bool
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        return (bool) ($attribute && $attribute->getId());
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
        $categoryIds = array_map(intval(...), $categoryIds);
        $product->setCategoryIds($categoryIds);
        $this->safeSave($product, 'assign categories');
    }

    /**
     * Resolve the stock changes the caller supplied, coalescing the structured
     * stockData map (snake_case or camelCase keys) and the flat stockQty
     * shortcut. Extended inventory columns (min_qty, backorders, the
     * use_config_* family, ...) are extracted alongside the core trio. Returns
     * null when the request carries no stock change to apply (matching the
     * original early-return: only manage_stock with no qty/availability and no
     * extended columns is treated as "nothing to do").
     *
     * @return array{qty: ?float, isInStock: ?bool, manageStock: ?bool, extended: array<string, int|float>}|null
     */
    private function extractStockInput(Product $data): ?array
    {
        $qty = null;
        $isInStock = null;
        $manageStock = null;
        $extended = [];

        if ($data->stockData !== null) {
            $stockData = [];
            foreach ($data->stockData as $key => $value) {
                $stockData[Product::camelToSnake((string) $key)] = $value;
            }
            $qty = isset($stockData['qty']) ? (float) $stockData['qty'] : null;
            $isInStock = isset($stockData['is_in_stock']) ? (bool) $stockData['is_in_stock'] : null;
            $manageStock = isset($stockData['manage_stock']) ? (bool) $stockData['manage_stock'] : null;
            $extended = $this->extractExtendedStockColumns($stockData);
        }

        if ($qty === null && $data->stockQty !== null) {
            $qty = $data->stockQty;
        }

        if ($qty === null && $isInStock === null && $extended === []) {
            return null;
        }

        return ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock, 'extended' => $extended];
    }

    private function updateStockData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        $input = $this->extractStockInput($data);
        if ($input === null) {
            return;
        }
        ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock, 'extended' => $extended] = $input;

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        // Stock_Item::_beforeSave() resets qty to 0 unless isQty() recognises the
        // product type. It normally reads type_id off the joined product row, which
        // a product with no stock row yet cannot supply, so state it explicitly.
        $stockItem->setProductTypeId($product->getTypeId());

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
            $stockItem->setIsInStock((bool) $isInStock);
        }

        // Only touch manage_stock when the caller explicitly provides it; otherwise
        // preserve the existing setting and default to enabled only for new items.
        if ($manageStock !== null) {
            $stockItem->setManageStock((bool) $manageStock);
        } elseif ($isNew) {
            $stockItem->setManageStock();
        }

        foreach ($extended as $column => $value) {
            $stockItem->setData($column, $value);
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
        ['qty' => $qty, 'isInStock' => $isInStock, 'manageStock' => $manageStock, 'extended' => $extended] = $input;

        if ($qty !== null) {
            $this->validateStockQty($qty);
        }

        $stockData = array_merge($this->buildStockData($qty, $isInStock, $manageStock), $extended);
        $this->upsertStockItemRow($productId, $stockData);
    }

    /**
     * Direct SQL category update for fast-update mode.
     */
    private function updateCategoriesDirect(int $productId, array $categoryIds): void
    {
        $categoryIds = array_map(intval(...), $categoryIds);

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/category_product');

        $existing = $write->fetchCol(
            "SELECT category_id FROM {$table} WHERE product_id = ?",
            [$productId],
        );
        $existing = array_map(intval(...), $existing);

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

    /**
     * Normalize a date input to the midnight 'Y-m-d H:i:s' form the admin
     * stores; empty string clears the value (returns null).
     */
    private function normalizeDateInput(string $value, string $field): ?string
    {
        // formatDateForDb() reads a numeric string as a unix timestamp, so an
        // unseparated date such as "20260801" would silently store 1970-08-22.
        if (is_numeric($value)) {
            throw new BadRequestHttpException("Invalid date for {$field}; use Y-m-d format.");
        }
        try {
            $date = Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Exception) {
            throw new BadRequestHttpException("Invalid date for {$field}; use Y-m-d format.");
        }
        return $date === null ? null : $date . ' 00:00:00';
    }

    private function validateDateRange(?string $from, ?string $to, string $fromField, string $toField): void
    {
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestHttpException("{$fromField} must not be later than {$toField}.");
        }
    }

    /**
     * Write responses mirror a GET of the saved product for the same caller.
     * The fresh load also shows the values that the fast path writes directly.
     */
    private function refreshDto(Mage_Catalog_Model_Product $product): Product
    {
        $productId = (int) $product->getId();
        // The inventory observer keeps the stock item of each loaded product for
        // the request. Clear it, or the fresh load shows the old stock values.
        $product->clearInstance();

        return new ProductProvider($this->security)->getItem($productId)
            ?? throw new NotFoundHttpException('Product not found');
    }

}
