<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 configurable variant prices (WRITE)
 *
 * The cart prices a variant as the parent price plus the price of each selected
 * option, and ignores the own price of the child. The variants of the product
 * API must show the price the cart charges.
 *
 * @group write
 */

function variantPriceWrite(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function variantPriceTable(string|array $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

function variantPriceAttributeId(string $code): int
{
    return (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code)->getId();
}

/**
 * The price with a percent option price, rounded as the cart rounds its line price
 */
function variantPriceWithPercent(float $base, float $percent): float
{
    return round($base + $base * ($percent / 100), 2);
}

/**
 * A configurable select attribute of the default attribute set with two options
 */
function variantPriceCreateAttribute(): Mage_Catalog_Model_Resource_Eav_Attribute
{
    $productType = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY);
    $setId = (int) $productType->getDefaultAttributeSetId();
    $attribute = Mage::getModel('catalog/resource_eav_attribute');
    $attribute->addData([
        'entity_type_id' => (int) $productType->getId(),
        'attribute_code' => 'pest_variant_' . substr(uniqid(), -8),
        'frontend_input' => 'select',
        'backend_type' => 'int',
        'frontend_label' => ['Pest variant'],
        'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'is_configurable' => 1,
        'is_user_defined' => 1,
        'option' => ['value' => ['first' => ['First'], 'second' => ['Second']]],
        'attribute_set_id' => $setId,
        'attribute_group_id' => (int) Mage::getModel('eav/entity_attribute_set')->getDefaultGroupId($setId),
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();

    return $attribute;
}

/**
 * @param array<string, mixed> $data
 */
function variantPriceCreateProduct(string $type, array $data): Mage_Catalog_Model_Product
{
    $sku = "pest-variant-{$type}-" . uniqid();
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId($type)
        ->setAttributeSetId((int) Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)->setName("Pest variant {$type}")->setUrlKey($sku)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->addData($data);
    $product->save();
    trackCreated('product', (int) $product->getId());

    return $product;
}

beforeAll(function (): void {
    Mage::app();
});

beforeEach(function (): void {
    // Made here, so the prices under test do not depend on the sample data
    $this->attribute = variantPriceCreateAttribute();
    $code = (string) $this->attribute->getAttributeCode();
    $options = array_values(array_filter(
        $this->attribute->getSource()->getAllOptions(false),
        fn(array $option) => $option['value'] !== '',
    ));

    // A child price apart from the parent price shows that the child price plays no part
    $childIds = [];
    foreach ($options as $option) {
        $childIds[] = (int) variantPriceCreateProduct(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 150.0,
            $code => (int) $option['value'],
            'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
        ])->getId();
    }
    $parent = variantPriceCreateProduct(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE, [
        'price' => 190.0,
        'can_save_configurable_attributes' => true,
        'configurable_attributes_data' => [['attribute_id' => (int) $this->attribute->getId(), 'position' => 0]],
        'configurable_products_data' => array_fill_keys($childIds, []),
    ]);

    $parent = Mage::getModel('catalog/product')->setStoreId(1)->load($parent->getId());
    /** @var Mage_Catalog_Model_Product_Type_Configurable $type */
    $type = $parent->getTypeInstance(true);
    $child = Mage::getModel('catalog/product')->load($childIds[0]);

    $this->parentId = (int) $parent->getId();
    $this->parentPrice = (float) $parent->getPrice();
    $this->childId = (int) $child->getId();
    $this->childSku = (string) $child->getSku();
    $this->pricingTable = variantPriceTable('catalog/product_super_attribute_pricing');
    $this->pricingWhere = [
        'product_super_attribute_id' => (int) $type->getConfigurableAttributes($parent)->getFirstItem()->getId(),
        'value_index' => (int) $child->getData($code),
    ];
    Mage::app()->cleanCache();
});

afterEach(function (): void {
    // Deleting the products also deletes their option prices
    cleanupTestData();
    Mage::getModel('catalog/resource_eav_attribute')->load((int) $this->attribute->getId())->delete();
    Mage::getSingleton('eav/config')->clear();
    Mage::app()->cleanCache();
});

function variantPriceAddOptionPrice(object $test, float $value, bool $isPercent): void
{
    variantPriceWrite()->insert($test->pricingTable, $test->pricingWhere + [
        'is_percent' => (int) $isPercent,
        'pricing_value' => $value,
        'website_id' => 0,
    ]);
    Mage::app()->cleanCache(['API_PRODUCTS']);
}

function variantPriceSetSpecialPrice(object $test, float $value): void
{
    variantPriceWrite()->insert(variantPriceTable(['catalog/product', 'decimal']), [
        'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId(),
        'attribute_id' => variantPriceAttributeId('special_price'),
        'store_id' => 0,
        'entity_id' => $test->parentId,
        'value' => $value,
    ]);
    Mage::app()->cleanCache(['API_PRODUCTS']);
}

/**
 * @return array{product: array<string, mixed>, variant: array<string, mixed>, cartPrice: float}
 */
function variantPriceRead(object $test): array
{
    $product = apiGet("/api/rest/v2/products/{$test->parentId}");
    expect($product['status'])->toBe(200);
    $variant = [];
    foreach ($product['json']['variants'] as $row) {
        if ((int) $row['id'] === $test->childId) {
            $variant = $row;
        }
    }

    $cart = apiPost('/api/rest/v2/guest-carts', []);
    trackCreated('quote', (int) $cart['json']['id']);
    $maskedId = $cart['json']['maskedId'];
    $added = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", ['sku' => $test->childSku, 'qty' => 1]);
    expect($added['status'])->toBeIn([200, 201]);
    $cartPrice = (float) apiGet("/api/rest/v2/guest-carts/{$maskedId}")['json']['items'][0]['price'];

    return ['product' => $product['json'], 'variant' => $variant, 'cartPrice' => $cartPrice];
}

it('prices a variant at the parent price, not the price of the child', function (): void {
    $read = variantPriceRead($this);

    expect($read['cartPrice'])->toBe($this->parentPrice)
        ->and((float) $read['variant']['price'])->toBe($this->parentPrice)
        ->and((float) $read['variant']['finalPrice'])->toBe($this->parentPrice);
});

it('adds a fixed option price to the variant price', function (): void {
    variantPriceAddOptionPrice($this, 10, false);

    $read = variantPriceRead($this);

    expect($read['cartPrice'])->toBe($this->parentPrice + 10)
        ->and((float) $read['variant']['price'])->toBe($this->parentPrice + 10)
        ->and((float) $read['variant']['finalPrice'])->toBe($this->parentPrice + 10);
});

it('adds a percent option price to the variant price, rounded as the cart line', function (): void {
    variantPriceAddOptionPrice($this, 3.333, true);

    $read = variantPriceRead($this);
    $expected = variantPriceWithPercent($this->parentPrice, 3.333);

    expect($read['cartPrice'])->toBe($expected)
        ->and((float) $read['variant']['price'])->toBe($expected)
        ->and((float) $read['variant']['finalPrice'])->toBe($read['cartPrice']);
});

it('takes the percent option price of the regular price for price and of the special price for finalPrice', function (): void {
    $specialPrice = $this->parentPrice - 40;
    variantPriceSetSpecialPrice($this, $specialPrice);
    variantPriceAddOptionPrice($this, 10, true);

    $read = variantPriceRead($this);

    expect((float) $read['product']['price'])->toBe($this->parentPrice)
        ->and((float) $read['product']['finalPrice'])->toBe($specialPrice)
        ->and($read['cartPrice'])->toBe(variantPriceWithPercent($specialPrice, 10))
        ->and((float) $read['variant']['price'])->toBe(variantPriceWithPercent($this->parentPrice, 10))
        ->and((float) $read['variant']['finalPrice'])->toBe($read['cartPrice']);
});

it('never prices a variant below zero', function (): void {
    variantPriceAddOptionPrice($this, -($this->parentPrice + 10), false);

    $read = variantPriceRead($this);

    expect($read['cartPrice'])->toBe(0.0)
        ->and((float) $read['variant']['price'])->toBe(0.0)
        ->and((float) $read['variant']['finalPrice'])->toBe(0.0);
});
