<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('matches the displayed fixed tax to the cart total after currency conversion and discount rounding', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.72]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        Mage::app()->setCurrentStore($storeId);
        foreach ([
            Mage_Weee_Helper_Data::XML_PATH_FPT_ENABLED => '1',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY => 'US',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => '0',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT => '1',
            'tax/weee/discount' => '1',
            'tax/weee/apply_vat' => '0',
            'tax/weee/include_in_subtotal' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $attribute = 'cart_fee_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $group = Mage::getModel('customer/group')
            ->setCode('Tax display test ' . uniqid())
            ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
            ->save();
        $groupId = (int) $group->getId();
        Mage::getModel('salesrule/rule')
            ->setName('Tax display test ' . uniqid())
            ->setIsActive(true)
            ->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(10.0)
            ->save();

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
        $address = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);
        for ($line = 0; $line < 3; $line++) {
            $fixture = WeeeDiscountFixture::createProduct('display-fee-' . uniqid(), $attribute, [$websiteId], 1.0, feeAmount: 0.12);
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($fixture['product']->getId());
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        }
        $cart->collectAndSave($quote);

        $adminStore = Mage::app()->getStore(Mage_Core_Model_Store::ADMIN_CODE);
        $adminStore->setConfig('tax/weee/include_in_subtotal', '1');
        Mage::app()->setCurrentStore($adminStore);
        $shippingAddress = $quote->getShippingAddress();
        $taxTotal = $shippingAddress->getTotals()['tax'];
        $block = new Mage_Tax_Block_Checkout_Tax()->setTotal($taxTotal);
        $helper = Mage::helper('weee');
        $items = $quote->getAllVisibleItems();
        expect($quote->getQuoteCurrencyCode())->toBe('EUR')
            ->and($block->getAllWeee())->toBe(['Test fixed tax' => 0.24])
            ->and($store->roundPrice(array_sum(array_map($helper->getRowWeeeAmountAfterDiscount(...), $items))))->toBe(0.24)
            ->and($store->roundPrice(array_sum(array_map($helper->getBaseRowWeeeAmountAfterDiscount(...), $items))))->toBe(0.32)
            ->and($shippingAddress->getTaxAmount())->toEqualWithDelta(0.24, 0.0001)
            ->and($shippingAddress->getBaseTaxAmount())->toEqualWithDelta(0.32, 0.0001)
            ->and($quote->getGrandTotal())->toEqualWithDelta(2.18, 0.0001);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
});

it('reads the old document discount only once per row when displaying two fixed taxes', function (
    string $documentModel,
    bool $json,
    bool $missingDetails = false,
): void {
    $store = Mage::app()->getStore(1);
    Mage::app()->setCurrentStore($store);
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    $store->setConfig('tax/weee/apply_vat', '0');
    $item = Mage::getModel($documentModel . '_item');
    $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId());
    $document = Mage::getModel($documentModel)->setOrder($order);
    $document->addItem($item);
    $applied = [
        [
            'title' => 'Battery fee', 'row_amount' => 0.432, 'base_row_amount' => 0.54,
            'weee_discount' => 0.12, 'base_weee_discount' => 0.15,
            'total_weee_discount' => 0.24, 'total_base_weee_discount' => 0.30,
        ],
        [
            'title' => 'Packaging fee', 'row_amount' => 0.768, 'base_row_amount' => 0.96,
            'weee_discount' => 0.12, 'base_weee_discount' => 0.15,
            'total_weee_discount' => 0.24, 'total_base_weee_discount' => 0.30,
        ],
    ];
    $firstTitle = 'Battery fee';
    if ($missingDetails) {
        unset($applied[0]['title'], $applied[0]['weee_discount'], $applied[0]['base_weee_discount']);
        $firstTitle = 'Fixed Product Tax';
        $store->setConfig('tax/weee/apply_vat', Mage_Weee_Helper_Data::LOADED_AND_DISPLAY_WITH_TAX);
    }
    $stored = $json ? json_encode($applied, JSON_THROW_ON_ERROR) : serialize($applied);
    $item->setWeeeTaxApplied($stored);

    $adminStore = Mage::app()->getStore(Mage_Core_Model_Store::ADMIN_CODE);
    $adminStore->setConfig('tax/weee/include_in_subtotal', '1');
    Mage::app()->setCurrentStore($adminStore);
    $helper = Mage::helper('weee');
    expect(Mage::helper('tax')->getAllWeee($document))->toBe([$firstTitle => 0.39, 'Packaging fee' => 0.69])
        ->and($helper->getRowWeeeAmountAfterDiscount($item))->toEqualWithDelta(1.08, 0.0001)
        ->and($helper->getBaseRowWeeeAmountAfterDiscount($item))->toEqualWithDelta(1.35, 0.0001)
        ->and($item->getWeeeTaxApplied())->toBe($stored);
})->with([
    'invoice stored as JSON' => ['sales/order_invoice', true],
    'credit memo stored as PHP data' => ['sales/order_creditmemo', false],
    'invoice without the first title and discount fields' => ['sales/order_invoice', true, true],
]);

it('reads legacy order fees with missing discount details', function (): void {
    $store = Mage::app()->getStore(1);
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId());
    $item = Mage::getModel('sales/order_item');
    $order->addItem($item);
    $helper = Mage::helper('weee');

    expect($helper->getRowWeeeAmountAfterDiscount($item))->toBe(0.0)
        ->and($helper->getBaseRowWeeeAmountAfterDiscount($item))->toBe(0.0);

    $item->setWeeeTaxApplied(serialize([['title' => 'Legacy fee', 'row_amount' => '1.20', 'base_row_amount' => '1.50']]));

    expect($helper->getRowWeeeAmountAfterDiscount($item))->toBe(1.2)
        ->and($helper->getBaseRowWeeeAmountAfterDiscount($item))->toBe(1.5);
});

it('uses the item store to display fixed tax when an extension supplies no quote', function (): void {
    $store = Mage::app()->getStore(1);
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    $admin = Mage::app()->getStore(Mage_Core_Model_Store::ADMIN_CODE);
    $admin->setConfig('tax/weee/include_in_subtotal', '1');
    Mage::app()->setCurrentStore($admin);

    $item = new class extends Mage_Sales_Model_Quote_Item {
        #[\Override]
        public function getQuote(): ?Mage_Sales_Model_Quote
        {
            return null;
        }
    };
    $item->setStoreId((int) $store->getId());
    $helper = Mage::helper('weee');
    $helper->setApplied($item, [
        ['title' => 'Fixed Product Tax', 'row_amount' => 0.12, 'base_row_amount' => 0.15, 'weee_discount' => 0.01, 'base_weee_discount' => 0.02],
    ]);

    expect($helper->getRowWeeeAmountAfterDiscount($item))->toEqualWithDelta(0.11, 0.0001)
        ->and($helper->getBaseRowWeeeAmountAfterDiscount($item))->toEqualWithDelta(0.13, 0.0001);
});

it('carries rounding between displayed fixed tax titles when the fees include tax', function (): void {
    $store = Mage::app()->getStore(1);
    Mage::app()->setCurrentStore($store);
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    $store->setConfig('tax/weee/apply_vat', Mage_Weee_Helper_Data::LOADED_AND_DISPLAY_WITH_TAX);
    $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId());
    $item = Mage::getModel('sales/order_item');
    $order->addItem($item);
    Mage::helper('weee')->setApplied($item, [
        ['title' => 'Battery fee', 'row_amount' => 0.02, 'row_amount_incl_tax' => 0.023, 'weee_discount' => 0.01],
        ['title' => 'Packaging fee', 'row_amount' => 0.02, 'row_amount_incl_tax' => 0.023, 'weee_discount' => 0.01],
    ]);

    expect(Mage::helper('tax')->getAllWeee($order))->toBe(['Battery fee' => 0.01, 'Packaging fee' => 0.02]);
});

it('uses the document store rounding to allocate each fixed tax discount', function (): void {
    $store = new class extends Mage_Core_Model_Store {
        #[\Override]
        public function roundPrice($price): float
        {
            return round((float) $price, 3);
        }
    };
    $applied = [
        ['title' => 'Battery fee', 'row_amount' => 1.0, 'base_row_amount' => 2.0],
        ['title' => 'Packaging fee', 'row_amount' => 2.0, 'base_row_amount' => 1.0],
    ];
    $allocated = Mage::helper('weee')->allocateTaxDiscounts($applied, 0.137, 0.263, $store);

    expect(array_column($allocated, 'weee_discount'))->toBe([0.046, 0.091])
        ->and(array_column($allocated, 'base_weee_discount'))->toBe([0.175, 0.088])
        ->and(array_column($allocated, 'total_weee_discount'))->toBe([0.137, 0.137])
        ->and(array_column($allocated, 'total_base_weee_discount'))->toBe([0.263, 0.263]);
});
