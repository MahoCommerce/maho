<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

$taxConfigPaths = [
    'tax/calculation/price_includes_tax',
    'tax/calculation/cross_border_trade_enabled',
    'tax/display/type',
];
$originalTaxConfig = [];

beforeEach(function () use ($taxConfigPaths, &$originalTaxConfig): void {
    $store = Mage::app()->getStore();
    foreach ($taxConfigPaths as $path) {
        $originalTaxConfig[$path] = $store->getConfig($path);
    }
});

afterEach(function () use (&$originalTaxConfig): void {
    $store = Mage::app()->getStore();
    foreach ($originalTaxConfig as $path => $value) {
        $store->setConfig($path, $value);
    }
});

/** Prices are stored excluding tax and displayed including tax, the setup that exposed the bug. */
$vatStore = static function (): Mage_Core_Model_Store {
    $store = Mage::app()->getStore();
    $store->setConfig('tax/calculation/price_includes_tax', 0);
    $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);

    return $store;
};

/** A product carrying a single 22% rate, so the templates never hit the database for one. */
$vatProduct = static function (Mage_Core_Model_Store $store, string $typeId, array $data): Mage_Catalog_Model_Product {
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId($typeId)
        ->setStoreId($store->getId())
        ->setTaxPercent(22)
        ->setAppliedRates([['percent' => 22]])
        ->addData($data);
};

$render = static function (string $blockAlias, string $template, Mage_Catalog_Model_Product $product, array $blockData = []): string {
    return Mage::app()->getLayout()->createBlock($blockAlias)
        ->addData($blockData)
        ->setTemplate($template)
        ->setProduct($product)
        ->toHtml();
};

$money = static fn(float $price): string => trim(strip_tags(Mage::helper('core')->formatPrice($price, false)));

describe('catalog price templates tax rounding', function () use ($vatStore, $vatProduct, $render, $money) {
    it('adds tax to the full 4-decimal price instead of the price rounded to 2 decimals', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.6557,
            'final_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });

    it('keeps prices with 2 decimals unchanged', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.01));
    });

    it('applies the same rounding in the rss price block', function () use ($vatStore, $vatProduct, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.6557,
            'final_price' => 10.6557,
        ]);

        $html = Mage::app()->getLayout()->createBlock('rss/catalog_new')->getPriceHtml($product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });

    it('applies the same rounding in the gift card price template', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, 'giftcard', [
            'giftcard_type' => 'fixed',
            'giftcard_amounts' => '10.6557,25',
        ]);

        $html = $render('giftcard/catalog_product_price', 'maho/giftcard/catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });

    it('rounds the price of a product without a tax class on a tax-inclusive store', function () {
        $store = Mage::app()->getStore();
        $store->setConfig('tax/calculation/price_includes_tax', 1);
        $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);

        $product = Mage::getModel('catalog/product')
            ->setId(1)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setStoreId($store->getId())
            ->setTaxClassId(0);

        expect(Mage::helper('tax')->getPrice($product, 10.665))->toBe(10.67);
    });

    it('backs the tax out to the same amount whether the caller asks explicitly or by display type', function () use ($vatProduct) {
        $store = Mage::app()->getStore();
        $store->setConfig('tax/calculation/price_includes_tax', 1);
        $store->setConfig('tax/calculation/cross_border_trade_enabled', 1);
        $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX);

        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, ['tax_class_id' => 2]);

        $taxHelper = Mage::helper('tax');

        expect($taxHelper->getPrice($product, 10.665, false))->toBe($taxHelper->getPrice($product, 10.665))
            ->and($taxHelper->getPrice($product, 10.665, false))->toBe(8.75);
    });
});

describe('catalog price templates minimal price visibility', function () use ($vatStore, $vatProduct, $render, $money) {
    it('hides the "From" price when it displays the same amount as the final price', function () use ($vatStore, $vatProduct, $render) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.6557,
            'final_price' => 10.6557,
            'minimal_price' => 10.6550,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->not->toContain('minimal-price-link');
    });

    it('shows the "From" price when it displays less, even though both amounts round alike before tax', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
            'minimal_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->toContain('minimal-price-link')->toContain($money(13.00))->toContain($money(13.01));
    });

    it('shows the "From" price when it is genuinely lower', function () use ($vatStore, $vatProduct, $render) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
            'minimal_price' => 5.00,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->toContain('minimal-price-link');
    });

    it('hides the "From" price on a tax-inclusive store that displays prices excluding tax', function () use ($vatProduct, $render) {
        $store = Mage::app()->getStore();
        $store->setConfig('tax/calculation/price_includes_tax', 1);
        $store->setConfig('tax/calculation/cross_border_trade_enabled', 1);
        $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX);

        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'tax_class_id' => 2,
            'price' => 10.6557,
            'final_price' => 10.6557,
            'minimal_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->not->toContain('minimal-price-link');
    });

    it('applies the same "From" price guard in the rss price block', function () use ($vatStore, $vatProduct) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
            'minimal_price' => 10.6557,
        ]);

        $html = Mage::app()->getLayout()->createBlock('rss/catalog_new')->getPriceHtml($product, true);

        expect($html)->toContain('minimal-price-link');
    });
});

describe('catalog price templates discount badge', function () use ($vatStore, $vatProduct, $render, $money) {
    it('drops the regular price and the badge when the discount rounds to zero percent', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))
            ->not->toContain($money(13.01))
            ->not->toContain('old-price')
            ->not->toContain('discount-percent');
    });

    it('shows the regular price and the badge when the discount is at least one percent', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 8.00,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain('old-price')->toContain($money(13.01))
            ->toContain('discount-percent')->toContain('-25%');
    });

    it('shows the badge in the rss price block, which resolves to the shared template', function () use ($vatStore, $vatProduct, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 8.00,
        ]);

        $html = Mage::app()->getLayout()->createBlock('rss/catalog_new')->getPriceHtml($product);

        expect($html)->toContain('old-price')->toContain($money(13.01))
            ->toContain('discount-percent')->toContain('-25%');
    });
});

describe('msrp price template', function () use ($render) {
    $msrpConfigPaths = ['sales/msrp/enabled', 'sales/msrp/display_price_type'];
    $originalMsrpConfig = [];

    beforeEach(function () use ($msrpConfigPaths, &$originalMsrpConfig): void {
        $store = Mage::app()->getStore();
        foreach ($msrpConfigPaths as $path) {
            $originalMsrpConfig[$path] = $store->getConfig($path);
        }
        $store->setConfig('sales/msrp/enabled', 1);
        $store->setConfig('sales/msrp/display_price_type', Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type::TYPE_ON_GESTURE);
    });

    afterEach(function () use (&$originalMsrpConfig): void {
        $store = Mage::app()->getStore();
        foreach ($originalMsrpConfig as $path => $value) {
            $store->setConfig($path, $value);
        }
    });

    $msrpProduct = static fn(): Mage_Catalog_Model_Product => Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setStoreId(Mage::app()->getStore()->getId())
        ->addData([
            'name' => 'MAP Product',
            'msrp' => 99.99,
            'msrp_enabled' => Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Enabled::MSRP_ENABLE_YES,
            'msrp_display_actual_price_type' => Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type::TYPE_ON_GESTURE,
            'real_price_html' => '<span class="real-price">SECRET-PRICE</span>',
        ]);

    it('degrades to a static product link in feeds and never leaks the real price', function () use ($render, $msrpProduct) {
        $product = $msrpProduct();

        $html = $render('catalog/product_price', 'catalog/product/price_msrp.phtml', $product, [
            'is_rss_feed' => true,
        ]);

        expect($html)->toContain('Click for price')
            ->toContain($product->getProductUrl())
            ->not->toContain('SECRET-PRICE')
            ->not->toContain('data-map-popup')
            ->not->toContain('<script');
    });

    it('renders a product link with popup data and no inline script on the storefront', function () use ($render, $msrpProduct) {
        $product = $msrpProduct();

        $html = $render('catalog/product_price', 'catalog/product/price_msrp.phtml', $product);

        expect($html)->toContain('data-map-popup')
            ->toContain('href="' . $product->getProductUrl() . '"')
            ->toContain('old-price')
            ->not->toContain('<script');
    });

    it('flags the price renderer as feed output in the rss blocks', function () {
        $catalogHtml = Mage::app()->getLayout()->createBlock('rss/catalog_new')
            ->getPriceHtml(Mage::getModel('catalog/product')->setId(1)->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE));
        expect($catalogHtml)->not->toContain('data-map-popup');

        $wishlistRenderer = Mage::app()->getLayout()->createBlock('rss/wishlist')->_preparePriceRenderer('msrp_rss');
        expect($wishlistRenderer->getIsRssFeed())->toBeTrue();
    });
});

describe('bundle price template in the rss price block', function () use ($vatStore, $money) {
    it('applies tax once to indexed bundle prices', function () use ($vatStore, $money) {
        $store = $vatStore();
        $product = Mage::getModel('catalog/product')
            ->setId(1)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
            ->setStoreId($store->getId())
            ->setTaxPercent(22)
            ->setAppliedRates([['percent' => 22]])
            ->addData([
                'price_type' => Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC,
                'min_price' => 10.6557,
                'max_price' => 25.00,
            ]);

        $block = Mage::app()->getLayout()->createBlock('rss/catalog_new');
        $block->addPriceBlockType('bundle', 'bundle/catalog_product_price', 'bundle/catalog/product/price.phtml');

        $html = $block->getPriceHtml($product);

        // The removed RSS copy applied the display tax twice: 10.6557 * 1.22 * 1.22 = 15.86.
        expect($html)->toContain($money(13.00))->not->toContain($money(15.86));
    });
});

/** A store that prints both amounts, where the regular price is displayed including tax and the final price excluding it. */
$bothPricesStore = static function (): Mage_Core_Model_Store {
    $store = Mage::app()->getStore();
    $store->setConfig('tax/calculation/price_includes_tax', 0);
    $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH);

    return $store;
};

/** Stands in for a store with one 2.00 fixed product tax, taxed at 22%, printed inside the price. */
class PriceTemplateTest_WeeeHelper extends Mage_Weee_Helper_Data
{
    public const AMOUNT = 2.00;
    public const TAX_AMOUNT = 0.44;

    #[\Override]
    public function isEnabled($store = null): bool
    {
        return true;
    }

    #[\Override]
    public function isTaxable($store = null): bool
    {
        return true;
    }

    #[\Override]
    public function getPriceDisplayType($store = null): int
    {
        return 0;
    }

    #[\Override]
    public function typeOfDisplay($product, $compareTo = null, $zone = null, $store = null)
    {
        if (is_null($compareTo)) {
            return $this->getPriceDisplayType();
        }
        if (is_array($compareTo)) {
            return in_array($this->getPriceDisplayType(), $compareTo);
        }
        return $this->getPriceDisplayType() == $compareTo;
    }

    #[\Override]
    public function getProductWeeeAttributesForRenderer($product, $shipping = null, $billing = null, $website = null, $calculateTaxes = false): array
    {
        return [new Maho\DataObject([
            'name' => 'FPT',
            'amount' => self::AMOUNT,
            'tax_amount' => self::TAX_AMOUNT,
        ])];
    }

    #[\Override]
    public function getAmountForDisplay($product): float
    {
        return self::AMOUNT;
    }

    #[\Override]
    public function getOriginalAmountForDisplay(Mage_Catalog_Model_Product $product): float
    {
        return self::AMOUNT;
    }

    #[\Override]
    public function getOriginalAmountInclTaxes(Mage_Catalog_Model_Product $product): float
    {
        return self::AMOUNT + self::TAX_AMOUNT;
    }
}

describe('catalog price templates discount badge with a fixed product tax', function () use ($bothPricesStore, $vatProduct, $render, $money) {
    beforeEach(function (): void {
        Mage::unregister('_helper/weee');
        Mage::register('_helper/weee', new PriceTemplateTest_WeeeHelper());
    });

    afterEach(function (): void {
        Mage::unregister('_helper/weee');
    });

    it('reads the discount from the amounts it prints, not from two different tax conventions', function () use ($bothPricesStore, $vatProduct, $render, $money) {
        $store = $bothPricesStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 8.00,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        // 13.01 + 2.44 against 9.76 + 2.44, both including tax, is a discount of 21 percent.
        expect($html)->toContain($money(15.45))->toContain('-21%')
            ->not->toContain($money(15.01))->not->toContain('-33%');
    });
});
