<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Return an order that gift cards paid in full, with the credit memos $previous already refunded.
 *
 * @param list<Mage_Sales_Model_Order_Creditmemo> $previous
 */
function giftcardCreditmemoOrderWith(array $previous): Mage_Sales_Model_Order
{
    $order = new class extends Mage_Sales_Model_Order {
        /** @var list<Mage_Sales_Model_Order_Creditmemo> */
        public array $previous = [];

        #[\Override]
        public function getCreditmemosCollection()
        {
            return $this->previous;
        }
    };
    $order->previous = $previous;
    $order->setGiftcardAmount(100.00);
    $order->setBaseGiftcardAmount(100.00);
    $order->setGrandTotal(0.00);

    return $order;
}

function giftcardCreditmemoRefunded(int $id, float $amount): Mage_Sales_Model_Order_Creditmemo
{
    return Mage::getModel('sales/order_creditmemo')->setId($id)
        ->setBaseGiftcardAmount(-$amount)->setGiftcardAmount(-$amount);
}

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
        $this->order->setBaseGrandTotal(50.00);
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

        // Half of the order total is refunded, so half of the gift card
        expect((float) $creditmemo->getBaseGiftcardAmount())->toBe(-25.00);
        expect((float) $creditmemo->getGiftcardAmount())->toBe(-25.00);
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
});

describe('Creditmemo Total - Multiple Credit Memos', function () {
    test('refunds only the gift card amount that previous credit memos did not refund', function () {
        $order = giftcardCreditmemoOrderWith([giftcardCreditmemoRefunded(1, 50.00)]);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setId(2);
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(80.00);
        $creditmemo->setBaseGrandTotal(80.00);

        Mage::getModel('giftcard/total_creditmemo')->collect($creditmemo);

        expect((float) $creditmemo->getBaseGiftcardAmount())->toBe(-50.00)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(30.00);
    });

    test('refunds nothing to the gift card when previous credit memos refunded all of it', function () {
        $order = giftcardCreditmemoOrderWith([giftcardCreditmemoRefunded(1, 100.00)]);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setId(2);
        $creditmemo->setOrder($order);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);

        Mage::getModel('giftcard/total_creditmemo')->collect($creditmemo);

        expect($creditmemo->getBaseGiftcardAmount())->toBeNull()
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(50.00);
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
