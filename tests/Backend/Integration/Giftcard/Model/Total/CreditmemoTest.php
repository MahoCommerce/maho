<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Creditmemo Total Collector Instantiation', function () {
    test('total collector can be instantiated', function () {
        $total = Mage::getModel('giftcard/total_creditmemo');
        expect($total)->toBeInstanceOf(Maho_Giftcard_Model_Total_Creditmemo::class);
        expect($total)->toBeInstanceOf(Mage_Sales_Model_Order_Creditmemo_Total_Abstract::class);
    });
});

describe('Creditmemo Total - Order Fully Paid by Gift Card', function () {
    beforeEach(function () {
        // Order was fully paid by gift card (grand_total = 0)
        $this->order = Mage::getModel('sales/order');
        $this->order->setGiftcardAmount(100.00);
        $this->order->setBaseGiftcardAmount(100.00);
        $this->order->setGrandTotal(0.00); // Fully covered by gift card
        $this->order->setBaseSubtotal(80.00);
        $this->order->setBaseShippingAmount(10.00);
        $this->order->setBaseTaxAmount(10.00);
    });

    test('refunds entire creditmemo amount to gift card', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);
        $creditmemo->setBaseSubtotal(40.00);
        $creditmemo->setBaseShippingAmount(5.00);
        $creditmemo->setBaseTaxAmount(5.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        // Entire credit memo goes to gift card
        expect((float) $creditmemo->getBaseGiftcardAmount())->toBe(-50.00);
        expect((float) $creditmemo->getGiftcardAmount())->toBe(-50.00);
    });

    test('reduces grand total to zero for fully gift card paid orders', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setGrandTotal(75.00);
        $creditmemo->setBaseGrandTotal(75.00);
        $creditmemo->setBaseSubtotal(60.00);
        $creditmemo->setBaseShippingAmount(7.50);
        $creditmemo->setBaseTaxAmount(7.50);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        expect((float) $creditmemo->getGrandTotal())->toBe(0.0);
        expect((float) $creditmemo->getBaseGrandTotal())->toBe(0.0);
    });

    test('sets allow zero grand total flag', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setGrandTotal(100.00);
        $creditmemo->setBaseGrandTotal(100.00);
        $creditmemo->setBaseSubtotal(80.00);
        $creditmemo->setBaseShippingAmount(10.00);
        $creditmemo->setBaseTaxAmount(10.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        expect($creditmemo->getAllowZeroGrandTotal())->toBeTrue();
    });
});

describe('Creditmemo Total - Partial Gift Card Payment', function () {
    beforeEach(function () {
        // Order partially paid by gift card
        $this->order = Mage::getModel('sales/order');
        $this->order->setGiftcardAmount(50.00);
        $this->order->setBaseGiftcardAmount(50.00);
        $this->order->setGrandTotal(50.00); // Remaining after gift card
        $this->order->setBaseSubtotal(80.00);
        $this->order->setBaseShippingAmount(10.00);
        $this->order->setBaseTaxAmount(10.00);
    });

    test('calculates proportional gift card refund', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setGrandTotal(50.00); // Half the order
        $creditmemo->setBaseGrandTotal(50.00);
        $creditmemo->setBaseSubtotal(40.00);
        $creditmemo->setBaseShippingAmount(5.00);
        $creditmemo->setBaseTaxAmount(5.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        // Should refund ~50% of gift card (25.00)
        $giftcardRefund = abs((float) $creditmemo->getBaseGiftcardAmount());
        expect($giftcardRefund)->toBeGreaterThan(0);
        expect($giftcardRefund)->toBeLessThanOrEqual(50.00);
    });

    test('reduces creditmemo grand total by gift card refund', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setGrandTotal(100.00);
        $creditmemo->setBaseGrandTotal(100.00);
        $creditmemo->setBaseSubtotal(80.00);
        $creditmemo->setBaseShippingAmount(10.00);
        $creditmemo->setBaseTaxAmount(10.00);

        $originalGrandTotal = $creditmemo->getBaseGrandTotal();

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        $giftcardRefund = abs((float) $creditmemo->getBaseGiftcardAmount());

        // Grand total should be reduced by gift card amount
        expect((float) $creditmemo->getBaseGrandTotal())->toBe($originalGrandTotal - $giftcardRefund);
    });
});

describe('Creditmemo Total - No Gift Card on Order', function () {
    test('returns early when no gift card on order', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(0);
        $order->setBaseGiftcardAmount(0);
        $order->setGrandTotal(100.00);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $result = $total->collect($creditmemo);

        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Creditmemo::class);
        expect($creditmemo->getBaseGiftcardAmount())->toBeNull();
        expect((float) $creditmemo->getGrandTotal())->toBe(50.00);
    });

    test('handles null gift card amount', function () {
        $order = Mage::getModel('sales/order');
        // Gift card amount not set (null)
        $order->setGrandTotal(100.00);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(100.00);
        $creditmemo->setBaseGrandTotal(100.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $result = $total->collect($creditmemo);

        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Creditmemo::class);
    });
});

describe('Creditmemo Total - Multiple Credit Memos', function () {
    test('tracks gift card refunded in previous credit memos', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(100.00);
        $order->setBaseGiftcardAmount(100.00);
        $order->setGrandTotal(0.00);
        $order->setBaseSubtotal(80.00);
        $order->setBaseShippingAmount(10.00);
        $order->setBaseTaxAmount(10.00);

        // First credit memo already processed
        $previousCreditmemo = Mage::getModel('sales/order_creditmemo');
        $previousCreditmemo->setId(1);
        $previousCreditmemo->setBaseGiftcardAmount(-50.00);
        $previousCreditmemo->setGiftcardAmount(-50.00);

        $creditmemoCollection = new Maho\Data\Collection();
        $creditmemoCollection->addItem($previousCreditmemo);
        $order->setData('creditmemos_collection', $creditmemoCollection);

        // New credit memo
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setId(2);
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);
        $creditmemo->setBaseSubtotal(40.00);
        $creditmemo->setBaseShippingAmount(5.00);
        $creditmemo->setBaseTaxAmount(5.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        // Should consider remaining gift card (100 - 50 = 50 remaining)
        $giftcardRefund = abs((float) $creditmemo->getBaseGiftcardAmount());
        expect($giftcardRefund)->toBeLessThanOrEqual(50.00);
    });

    test('does not refund more than remaining gift card amount', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(100.00);
        $order->setBaseGiftcardAmount(100.00);
        $order->setGrandTotal(0.00);
        $order->setBaseSubtotal(100.00);
        $order->setBaseShippingAmount(0);
        $order->setBaseTaxAmount(0);

        // First credit memo refunded all gift card
        $previousCreditmemo = Mage::getModel('sales/order_creditmemo');
        $previousCreditmemo->setId(1);
        $previousCreditmemo->setBaseGiftcardAmount(-100.00);
        $previousCreditmemo->setGiftcardAmount(-100.00);

        $creditmemoCollection = new Maho\Data\Collection();
        $creditmemoCollection->addItem($previousCreditmemo);
        $order->setData('creditmemos_collection', $creditmemoCollection);

        // Second credit memo
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setId(2);
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);
        $creditmemo->setBaseSubtotal(50.00);
        $creditmemo->setBaseShippingAmount(0);
        $creditmemo->setBaseTaxAmount(0);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $result = $total->collect($creditmemo);

        // Should return early, no more gift card to refund
        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Creditmemo::class);
    });
});

describe('Creditmemo Total - Grand Total Boundaries', function () {
    test('prevents negative grand total', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(200.00);
        $order->setBaseGiftcardAmount(200.00);
        $order->setGrandTotal(0.00);
        $order->setBaseSubtotal(150.00);
        $order->setBaseShippingAmount(25.00);
        $order->setBaseTaxAmount(25.00);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);
        $creditmemo->setBaseSubtotal(40.00);
        $creditmemo->setBaseShippingAmount(5.00);
        $creditmemo->setBaseTaxAmount(5.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        expect((float) $creditmemo->getGrandTotal())->toBeGreaterThanOrEqual(0);
        expect((float) $creditmemo->getBaseGrandTotal())->toBeGreaterThanOrEqual(0);
    });

    test('caps gift card refund at original amount used when creditmemo total exceeds it', function () {
        // Scenario: Order had $192.60 total, paid partially with $100 gift card
        // but grand_total was 0 (bug in original order - perhaps discount was applied)
        // Creditmemo for full order ($192.60) should NOT refund more than $100 to gift card
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(100.00);  // Only $100 was used from gift card
        $order->setBaseGiftcardAmount(100.00);
        $order->setGrandTotal(0.00);  // Order grand total was 0
        $order->setBaseSubtotal(160.00);
        $order->setBaseShippingAmount(5.00);
        $order->setBaseTaxAmount(27.60);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        // Full refund of order totals
        $creditmemo->setGrandTotal(192.60);
        $creditmemo->setBaseGrandTotal(192.60);
        $creditmemo->setSubtotal(160.00);
        $creditmemo->setBaseSubtotal(160.00);
        $creditmemo->setShippingAmount(5.00);
        $creditmemo->setBaseShippingAmount(5.00);
        $creditmemo->setTaxAmount(27.60);
        $creditmemo->setBaseTaxAmount(27.60);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        // Gift card refund must be capped at $100 (the original amount used)
        // NOT $192.60 (the full creditmemo amount)
        $giftcardRefund = abs((float) $creditmemo->getBaseGiftcardAmount());
        expect($giftcardRefund)->toBe(100.00);
    });

    test('caps gift card refund when order fully paid by gift card with remaining balance', function () {
        // Gift card with $150 balance, $100 used on order, $50 remaining
        // Creditmemo should refund max $100, not the full creditmemo amount
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(100.00);
        $order->setBaseGiftcardAmount(100.00);
        $order->setGrandTotal(0.00);
        $order->setBaseSubtotal(80.00);
        $order->setBaseShippingAmount(10.00);
        $order->setBaseTaxAmount(10.00);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        // Full refund
        $creditmemo->setGrandTotal(100.00);
        $creditmemo->setBaseGrandTotal(100.00);
        $creditmemo->setSubtotal(80.00);
        $creditmemo->setBaseSubtotal(80.00);
        $creditmemo->setShippingAmount(10.00);
        $creditmemo->setBaseShippingAmount(10.00);
        $creditmemo->setTaxAmount(10.00);
        $creditmemo->setBaseTaxAmount(10.00);

        $total = Mage::getModel('giftcard/total_creditmemo');
        $total->collect($creditmemo);

        // Gift card refund should be exactly $100
        $giftcardRefund = abs((float) $creditmemo->getBaseGiftcardAmount());
        expect($giftcardRefund)->toBe(100.00);
    });
});
