<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The index is not the only path a price is read by: the product page and the cart load the price
 * attribute itself. That path derives the same value, from the default-scope amount and the rate,
 * and never stores what it derived. The currency is an ISO 4217 "X" code no real currency uses.
 */
const DERIVED_MODEL_CODE = 'derived_model_ws';
const DERIVED_MODEL_CURRENCY = 'XTM';
const DERIVED_MODEL_PRICE = 10.6557;
const DERIVED_MODEL_SPECIAL = 8.0;
const DERIVED_MODEL_RATE = 0.85;
// 10.6557 * 0.85 = 9.057345 and 8.0 * 0.85 = 6.8, at the four decimals the price column holds
const DERIVED_MODEL_EXPECTED = 9.0573;
const DERIVED_MODEL_EXPECTED_SPECIAL = 6.8;

function derivedModelWebsite(): Mage_Core_Model_Website
{
    return createPriceWebsite(DERIVED_MODEL_CODE, 96);
}

/** Price scope for every store, in memory; 0 puts the install back on global prices. */
function derivedModelSetPriceScope(int $scope): void
{
    if ($scope === 0) {
        restorePriceScope(DERIVED_MODEL_CODE);
    } else {
        configurePriceWebsite(DERIVED_MODEL_CODE, DERIVED_MODEL_CURRENCY, $scope);
    }
}

function derivedModelConfigure(): void
{
    configurePriceWebsite(DERIVED_MODEL_CODE, DERIVED_MODEL_CURRENCY);
}

function derivedModelDeleteWebsite(): void
{
    deletePriceWebsite(DERIVED_MODEL_CODE);
}

function derivedModelDropRate(): void
{
    dropCurrencyRates(DERIVED_MODEL_CURRENCY);
}

function derivedModelStoreId(): int
{
    return (int) Mage::app()->getStore(DERIVED_MODEL_CODE)->getId();
}

function derivedModelSaveRate(): void
{
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [DERIVED_MODEL_CURRENCY => DERIVED_MODEL_RATE],
    ]);
}

function derivedModelProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('derived-model', DERIVED_MODEL_PRICE, derivedModelWebsite(), [
        'special_price' => DERIVED_MODEL_SPECIAL,
    ]);
}

function derivedModelLoad(int $productId): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')->setStoreId(derivedModelStoreId())->load($productId);
}

/** The storefront runs with the website's own store current, so a collection load mirrors that. */
function derivedModelLoadFromCollection(int $productId): Mage_Catalog_Model_Product
{
    Mage::app()->setCurrentStore(derivedModelStoreId());

    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId(derivedModelStoreId())
        ->addAttributeToSelect(['price', 'special_price'])
        ->addIdFilter([$productId])
        ->getFirstItem();

    return $product;
}

/** @return array<int, float> store id to the stored attribute row */
function derivedModelStoredRows(int $productId, string $code): array
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $code);
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $rows = $adapter->fetchAll(
        $adapter->select()
            ->from($attribute->getBackend()->getTable(), ['store_id', 'value'])
            ->where('entity_id = ?', $productId)
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->order('store_id ASC'),
    );

    $stored = [];
    foreach ($rows as $row) {
        $stored[(int) $row['store_id']] = (float) $row['value'];
    }

    return $stored;
}

beforeEach(function () {
    $this->currentStore = Mage::app()->getStore()->getId();
    derivedModelWebsite();
    derivedModelConfigure();
    $this->product = derivedModelProduct();
    $this->productId = (int) $this->product->getId();
});

afterEach(function () {
    Mage::app()->setCurrentStore($this->currentStore);
    if (isset($this->product) && $this->product->getId()) {
        $this->product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->delete();
    }
    derivedModelSetPriceScope(0);
    derivedModelDropRate();
    derivedModelDeleteWebsite();
});

it('derives the base price from the default-scope value and the rate', function () {
    derivedModelSaveRate();

    expect(derivedModelLoad($this->productId)->getPrice())->toBe(DERIVED_MODEL_EXPECTED);
});

it('derives the special price too', function () {
    derivedModelSaveRate();

    expect(derivedModelLoad($this->productId)->getSpecialPrice())->toBe(DERIVED_MODEL_EXPECTED_SPECIAL);
});

it('leaves an explicit website price alone', function () {
    derivedModelSaveRate();
    $this->product->setStoreId(derivedModelStoreId())->setPrice(12.0)->save();

    expect(derivedModelLoad($this->productId)->getPrice())->toBe(12.0);
});

it('derives the same price when the product comes from a collection', function () {
    derivedModelSaveRate();

    expect(derivedModelLoadFromCollection($this->productId)->getPrice())->toBe(DERIVED_MODEL_EXPECTED);
});

/*
 * The regression the collection path invites: with no flag saying the value is the website's own,
 * an explicit price is converted a second time.
 */
it('leaves an explicit website price alone when the product comes from a collection', function () {
    derivedModelSaveRate();
    $this->product->setStoreId(derivedModelStoreId())->setPrice(12.0)->save();

    expect(derivedModelLoadFromCollection($this->productId)->getPrice())->toBe(12.0);
});

/*
 * A cron or CLI run has the admin store current, and a collection item carries no store of its own,
 * so without the collection saying which store it loaded for the rate would come out at parity.
 */
it('derives the price for the store the collection was loaded for, not the current one', function () {
    derivedModelSaveRate();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId(derivedModelStoreId())
        ->addAttributeToSelect(['price'])
        ->addIdFilter([$this->productId])
        ->getFirstItem();

    expect($product->getPrice())->toBe(DERIVED_MODEL_EXPECTED);
});

/*
 * A downloadable link is loaded for its product's website, so the rate has to come from the
 * product and not from whatever store the process happens to have current.
 */
it('derives a downloadable link price from the product store, not the current one', function () {
    derivedModelSaveRate();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    $product = Mage::getModel('catalog/product')->setStoreId(derivedModelStoreId());

    /** @var Mage_Downloadable_Model_Link $link */
    $link = Mage::getModel('downloadable/link')
        ->setProduct($product)
        ->setData('price', 20.0)
        ->setData('website_price', null);

    expect($link->getPrice())->toBe(17.0);
});

it('offers no price at all when the website has no rate', function () {
    expect(derivedModelLoad($this->productId)->getPrice())->toBeNull();
});

/*
 * fballiano's one condition on deriving at read time: a load/save cycle must not turn the derived
 * value into an explicit website price.
 */
it('does not write the derived price back on save', function () {
    derivedModelSaveRate();

    $product = derivedModelLoad($this->productId);
    expect($product->getPrice())->toBe(DERIVED_MODEL_EXPECTED);

    $product->save();

    expect(derivedModelStoredRows($this->productId, 'price'))
        ->toBe([Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID => DERIVED_MODEL_PRICE]);
});

/*
 * The flag that says "this value is the store's own" is set when a product is loaded. A value the
 * product has just saved for its store is its own as well, without a reload in between: anything
 * that reads the price in the same request, a save_after observer for one, must not convert it.
 */
it('treats a price just saved for the store as the store\'s own value', function () {
    derivedModelSaveRate();

    $product = derivedModelLoad($this->productId);
    $product->setPrice(12.0)->save();

    expect($product->getPrice())->toBe(12.0);
});

it('derives again once the store price is removed', function () {
    derivedModelSaveRate();

    $product = derivedModelLoad($this->productId);
    $product->setPrice(12.0)->save();
    $product->setPrice(false)->save();

    expect(derivedModelStoredRows($this->productId, 'price'))
        ->toBe([Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID => DERIVED_MODEL_PRICE])
        ->and(derivedModelLoad($this->productId)->getPrice())->toBe(DERIVED_MODEL_EXPECTED);
});

it('derives nothing while prices are global', function () {
    derivedModelSaveRate();
    derivedModelSetPriceScope(0);

    expect(derivedModelLoad($this->productId)->getPrice())->toBe(DERIVED_MODEL_PRICE);
});

/*
 * A null price reaches Type/Price::getBasePrice() as a (float) cast, so without this the product
 * would offer itself at zero rather than not at all.
 */
it('does not sell at all when the website has no rate', function () {
    expect(derivedModelLoad($this->productId)->isSalable())->toBeFalse();
});

it('sells normally once the website has a rate', function () {
    derivedModelSaveRate();

    expect(derivedModelLoad($this->productId)->isSalable())->toBeTrue();
});

it('offers no final price at all when the website has no rate', function () {
    $product = derivedModelLoad($this->productId);

    expect($product->getFinalPrice())->toEqual(0.0)
        ->and($product->isSalable())->toBeFalse();
});

/*
 * A bundle's special_price is a percentage of the final price, not an amount, so converting it
 * would change the discount rather than restate it. Its group and tier prices are percentages too
 * and are already left alone by isGroupPriceFixed().
 */
it('does not convert a special price that is a percentage', function () {
    derivedModelSaveRate();

    $bundle = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        ->setStoreId(derivedModelStoreId())
        ->setData('special_price', 80.0);

    expect($bundle->getSpecialPrice())->toBe(80.0);
});

it('still converts a special price that is an amount', function () {
    derivedModelSaveRate();

    $simple = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setStoreId(derivedModelStoreId())
        ->setData('special_price', DERIVED_MODEL_SPECIAL);

    expect($simple->getSpecialPrice())->toBe(DERIVED_MODEL_EXPECTED_SPECIAL);
});

/*
 * msrp shares the price backend and the website scope, and the storefront renders it through
 * currency(), so an underived value shows the default currency's number under another label.
 */
it('derives the msrp', function () {
    derivedModelSaveRate();

    $product = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setStoreId(derivedModelStoreId())
        ->setData('msrp', 100.0);

    expect($product->getMsrp())->toBe(85.0);
});

/*
 * The API hydrates its fields straight from the model's data, so without the derivation the REST
 * and GraphQL responses report the default-scope amounts under the store's own currency code.
 */
it('derives the price fields the API returns', function () {
    derivedModelSaveRate();

    $product = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setStoreId(derivedModelStoreId())
        ->setData('price', DERIVED_MODEL_PRICE)
        ->setData('special_price', DERIVED_MODEL_SPECIAL)
        ->setData('msrp', 100.0);

    $dto = new Mage\Catalog\Api\Product();
    $dto->price = DERIVED_MODEL_PRICE;
    $dto->specialPrice = DERIVED_MODEL_SPECIAL;
    $dto->msrp = 100.0;

    Mage\Catalog\Api\Product::afterLoad($dto, $product);

    expect($dto->price)->toBe(DERIVED_MODEL_EXPECTED);
    expect($dto->specialPrice)->toBe(DERIVED_MODEL_EXPECTED_SPECIAL);
    expect($dto->msrp)->toBe(85.0);
});

/*
 * A giftcard falls through to its amount list when the price attribute holds nothing, so a null
 * from a missing rate would have handed back an unconverted amount and put the product back on
 * sale at a figure in the wrong currency.
 */
it('offers a giftcard no price either when the website has no rate', function () {
    $product = Mage::getModel('catalog/product')
        ->setTypeId('giftcard')
        ->setStoreId(derivedModelStoreId())
        ->setData('giftcard_amounts', '50,100');

    expect($product->getPriceModel()->getPrice($product))->toBeNull();
});

it('still offers a giftcard its amount list when the website has a rate', function () {
    derivedModelSaveRate();

    $product = Mage::getModel('catalog/product')
        ->setTypeId('giftcard')
        ->setStoreId(derivedModelStoreId())
        ->setData('giftcard_amounts', '50,100');

    expect($product->getPriceModel()->getPrice($product))->toBe(50.0);
});
