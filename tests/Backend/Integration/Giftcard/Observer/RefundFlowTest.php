<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Refund Flow - Single Gift Card', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');

        // Create a gift card with known balance
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        // Create order that used the gift card
        $this->order = Mage::getModel('sales/order');
        $this->order->setIncrementId('REFUND-TEST-' . time() . '-' . mt_rand(1000, 9999));
        $this->order->setStoreId(1);
        $this->order->setStore(Mage::app()->getStore(1));
        $this->order->setBaseCurrencyCode('USD');
        $this->order->setOrderCurrencyCode('USD');
        $this->order->setBaseToOrderRate(1.0);
        $this->order->setBaseSubtotal(150.00);
        $this->order->setSubtotal(150.00);
        $this->order->setBaseShippingAmount(10.00);
        $this->order->setShippingAmount(10.00);
        $this->order->setBaseTaxAmount(0.00);
        $this->order->setTaxAmount(0.00);
        $this->order->setBaseGrandTotal(60.00); // 160 - 100 gift card
        $this->order->setGrandTotal(60.00);
        $this->order->setBaseGiftcardAmount(100.00);
        $this->order->setGiftcardAmount(100.00);
        $this->order->setGiftcardCodes(json_encode([$this->giftcard->getCode() => 100.00]));
        $this->order->save();

        // Simulate the gift card was used (deduct balance)
        $this->giftcard->setBalance(0.00);
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $this->giftcard->save();
    });

    test('restores full gift card balance on full refund', function () {
        // Create credit memo for full order
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-100.00); // Negative = refund to gift card
        $creditmemo->setGiftcardAmount(-100.00);
        $creditmemo->save();

        // Trigger the refund observer
        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        // Reload gift card and verify balance restored
        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());

        expect((float) $reloadedCard->getBalance())->toBe(100.00);
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });

    test('restores partial gift card balance on partial refund', function () {
        // Create credit memo for partial refund (50% of order)
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-50.00); // Refund half
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());

        expect((float) $reloadedCard->getBalance())->toBe(50.00);
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });

    test('creates history entry for refund', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-75.00);
        $creditmemo->setGiftcardAmount(-75.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $historyCountBefore = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard->getId())
            ->getSize();

        $this->observer->refundGiftcardBalance($eventObserver);

        $historyCountAfter = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard->getId())
            ->getSize();

        expect($historyCountAfter)->toBe($historyCountBefore + 1);

        // Verify history entry details
        $latestHistory = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard->getId())
            ->setOrder('created_at', 'DESC')
            ->getFirstItem();

        expect($latestHistory->getAction())->toBe(Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED);
        expect((float) $latestHistory->getBaseAmount())->toBe(75.00);
        expect((float) $latestHistory->getBalanceBefore())->toBe(0.00);
        expect((float) $latestHistory->getBalanceAfter())->toBe(75.00);
    });

    test('reactivates used gift card on refund', function () {
        // Verify card is used
        expect($this->giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        expect((float) $this->giftcard->getBalance())->toBe(0.00);

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-25.00);
        $creditmemo->setGiftcardAmount(-25.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());

        // Card should be reactivated even with partial refund
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        expect((float) $reloadedCard->getBalance())->toBe(25.00);
    });
});

describe('Refund Flow - Multiple Gift Cards', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');

        // Create two gift cards
        $this->giftcard1 = Mage::getModel('giftcard/giftcard');
        $this->giftcard1->setCode($this->helper->generateCode());
        $this->giftcard1->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard1->setWebsiteId(1);
        $this->giftcard1->setBalance(30.00);
        $this->giftcard1->setInitialBalance(30.00);
        $this->giftcard1->save();

        $this->giftcard2 = Mage::getModel('giftcard/giftcard');
        $this->giftcard2->setCode($this->helper->generateCode());
        $this->giftcard2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard2->setWebsiteId(1);
        $this->giftcard2->setBalance(70.00);
        $this->giftcard2->setInitialBalance(70.00);
        $this->giftcard2->save();

        // Create order that used both gift cards ($30 + $70 = $100)
        $this->order = Mage::getModel('sales/order');
        $this->order->setIncrementId('REFUND-MULTI-' . time() . '-' . mt_rand(1000, 9999));
        $this->order->setStoreId(1);
        $this->order->setStore(Mage::app()->getStore(1));
        $this->order->setBaseCurrencyCode('USD');
        $this->order->setOrderCurrencyCode('USD');
        $this->order->setBaseToOrderRate(1.0);
        $this->order->setBaseSubtotal(150.00);
        $this->order->setSubtotal(150.00);
        $this->order->setBaseShippingAmount(10.00);
        $this->order->setShippingAmount(10.00);
        $this->order->setBaseTaxAmount(0.00);
        $this->order->setTaxAmount(0.00);
        $this->order->setBaseGrandTotal(60.00); // 160 - 100 gift cards
        $this->order->setGrandTotal(60.00);
        $this->order->setBaseGiftcardAmount(100.00);
        $this->order->setGiftcardAmount(100.00);
        $this->order->setGiftcardCodes(json_encode([
            $this->giftcard1->getCode() => 30.00,
            $this->giftcard2->getCode() => 70.00,
        ]));
        $this->order->save();

        // Simulate both cards were used
        $this->giftcard1->setBalance(0.00);
        $this->giftcard1->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $this->giftcard1->save();

        $this->giftcard2->setBalance(0.00);
        $this->giftcard2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $this->giftcard2->save();
    });

    test('refunds proportionally to multiple gift cards', function () {
        // Refund $50 (half of the $100 gift card total)
        // Card1 contributed 30% ($30), Card2 contributed 70% ($70)
        // Expected: Card1 gets $15, Card2 gets $35
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-50.00);
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard1 = Mage::getModel('giftcard/giftcard')->load($this->giftcard1->getId());
        $reloadedCard2 = Mage::getModel('giftcard/giftcard')->load($this->giftcard2->getId());

        // Card1: (30/100) * 50 = 15
        expect((float) $reloadedCard1->getBalance())->toBe(15.00);

        // Card2: (70/100) * 50 = 35
        expect((float) $reloadedCard2->getBalance())->toBe(35.00);

        // Both should be reactivated
        expect($reloadedCard1->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        expect($reloadedCard2->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });

    test('refunds full amounts to multiple gift cards', function () {
        // Full refund of $100
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-100.00);
        $creditmemo->setGiftcardAmount(-100.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard1 = Mage::getModel('giftcard/giftcard')->load($this->giftcard1->getId());
        $reloadedCard2 = Mage::getModel('giftcard/giftcard')->load($this->giftcard2->getId());

        expect((float) $reloadedCard1->getBalance())->toBe(30.00);
        expect((float) $reloadedCard2->getBalance())->toBe(70.00);
    });

    test('creates history entries for both cards', function () {
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-100.00);
        $creditmemo->setGiftcardAmount(-100.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        // Check history for card 1
        $history1 = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard1->getId())
            ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
            ->getFirstItem();

        expect($history1->getId())->not->toBeNull();
        expect((float) $history1->getBaseAmount())->toBe(30.00);

        // Check history for card 2
        $history2 = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard2->getId())
            ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
            ->getFirstItem();

        expect($history2->getId())->not->toBeNull();
        expect((float) $history2->getBaseAmount())->toBe(70.00);
    });
});

describe('Refund Flow - Multiple Credit Memos', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');

        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        $this->order = Mage::getModel('sales/order');
        $this->order->setIncrementId('REFUND-MULTI-CM-' . time() . '-' . mt_rand(1000, 9999));
        $this->order->setStoreId(1);
        $this->order->setStore(Mage::app()->getStore(1));
        $this->order->setBaseCurrencyCode('USD');
        $this->order->setOrderCurrencyCode('USD');
        $this->order->setBaseToOrderRate(1.0);
        $this->order->setBaseSubtotal(200.00);
        $this->order->setSubtotal(200.00);
        $this->order->setBaseShippingAmount(0.00);
        $this->order->setShippingAmount(0.00);
        $this->order->setBaseTaxAmount(0.00);
        $this->order->setTaxAmount(0.00);
        $this->order->setBaseGrandTotal(100.00); // 200 - 100 gift card
        $this->order->setGrandTotal(100.00);
        $this->order->setBaseGiftcardAmount(100.00);
        $this->order->setGiftcardAmount(100.00);
        $this->order->setGiftcardCodes(json_encode([$this->giftcard->getCode() => 100.00]));
        $this->order->save();

        // Simulate card was used
        $this->giftcard->setBalance(0.00);
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $this->giftcard->save();
    });

    test('handles sequential refunds correctly', function () {
        // First refund: $40
        $creditmemo1 = Mage::getModel('sales/order_creditmemo');
        $creditmemo1->setOrder($this->order);
        $creditmemo1->setBaseGiftcardAmount(-40.00);
        $creditmemo1->setGiftcardAmount(-40.00);
        $creditmemo1->save();

        $event1 = new Maho\Event();
        $event1->setCreditmemo($creditmemo1);
        $eventObserver1 = new Maho\Event\Observer();
        $eventObserver1->setEvent($event1);

        $this->observer->refundGiftcardBalance($eventObserver1);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());
        expect((float) $reloadedCard->getBalance())->toBe(40.00);

        // Second refund: $30
        $creditmemo2 = Mage::getModel('sales/order_creditmemo');
        $creditmemo2->setOrder($this->order);
        $creditmemo2->setBaseGiftcardAmount(-30.00);
        $creditmemo2->setGiftcardAmount(-30.00);
        $creditmemo2->save();

        $event2 = new Maho\Event();
        $event2->setCreditmemo($creditmemo2);
        $eventObserver2 = new Maho\Event\Observer();
        $eventObserver2->setEvent($event2);

        $this->observer->refundGiftcardBalance($eventObserver2);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());
        expect((float) $reloadedCard->getBalance())->toBe(70.00); // 40 + 30

        // Third refund: $30 (remaining)
        $creditmemo3 = Mage::getModel('sales/order_creditmemo');
        $creditmemo3->setOrder($this->order);
        $creditmemo3->setBaseGiftcardAmount(-30.00);
        $creditmemo3->setGiftcardAmount(-30.00);
        $creditmemo3->save();

        $event3 = new Maho\Event();
        $event3->setCreditmemo($creditmemo3);
        $eventObserver3 = new Maho\Event\Observer();
        $eventObserver3->setEvent($event3);

        $this->observer->refundGiftcardBalance($eventObserver3);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());
        expect((float) $reloadedCard->getBalance())->toBe(100.00); // Fully restored
    });

    test('creates separate history entries for each refund', function () {
        // First refund
        $creditmemo1 = Mage::getModel('sales/order_creditmemo');
        $creditmemo1->setOrder($this->order);
        $creditmemo1->setBaseGiftcardAmount(-60.00);
        $creditmemo1->setGiftcardAmount(-60.00);
        $creditmemo1->save();

        $event1 = new Maho\Event();
        $event1->setCreditmemo($creditmemo1);
        $eventObserver1 = new Maho\Event\Observer();
        $eventObserver1->setEvent($event1);

        $this->observer->refundGiftcardBalance($eventObserver1);

        // Second refund
        $creditmemo2 = Mage::getModel('sales/order_creditmemo');
        $creditmemo2->setOrder($this->order);
        $creditmemo2->setBaseGiftcardAmount(-40.00);
        $creditmemo2->setGiftcardAmount(-40.00);
        $creditmemo2->save();

        $event2 = new Maho\Event();
        $event2->setCreditmemo($creditmemo2);
        $eventObserver2 = new Maho\Event\Observer();
        $eventObserver2->setEvent($event2);

        $this->observer->refundGiftcardBalance($eventObserver2);

        // Check history entries
        $historyCollection = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->giftcard->getId())
            ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
            ->setOrder('created_at', 'ASC');

        expect($historyCollection->getSize())->toBe(2);

        $histories = $historyCollection->getItems();
        $historyArray = array_values($histories);

        expect((float) $historyArray[0]->getBaseAmount())->toBe(60.00);
        expect((float) $historyArray[0]->getBalanceAfter())->toBe(60.00);

        expect((float) $historyArray[1]->getBaseAmount())->toBe(40.00);
        expect((float) $historyArray[1]->getBalanceAfter())->toBe(100.00);
    });
});

describe('Refund Flow - Edge Cases', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');
    });

    test('handles zero refund amount gracefully', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-ZERO-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 50.00]));
        $order->save();

        // Credit memo with zero gift card amount
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(0);
        $creditmemo->setGiftcardAmount(0);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Should not throw exception
        $this->observer->refundGiftcardBalance($eventObserver);

        // Balance should remain unchanged
        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect((float) $reloadedCard->getBalance())->toBe(0.00);
    });

    test('handles missing gift card codes gracefully', function () {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-NOCODES-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setGiftcardCodes(null); // No codes
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-50.00);
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Should not throw exception
        $this->observer->refundGiftcardBalance($eventObserver);

        expect(true)->toBeTrue();
    });

    test('handles deleted gift card gracefully', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        $code = $giftcard->getCode();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-DELETED-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setGiftcardCodes(json_encode([$code => 50.00]));
        $order->save();

        // Delete the gift card
        $giftcard->delete();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-50.00);
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Should not throw exception even if card is deleted
        $this->observer->refundGiftcardBalance($eventObserver);

        expect(true)->toBeTrue();
    });

    test('handles card with remaining balance correctly', function () {
        // Card had $100, only $60 was used on order, $40 remaining
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(40.00); // $40 remaining
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-PARTIAL-USE-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(60.00); // Only $60 was applied
        $order->setGiftcardAmount(60.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 60.00]));
        $order->save();

        // Refund $30 back to gift card
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-30.00);
        $creditmemo->setGiftcardAmount(-30.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be $40 + $30 = $70
        expect((float) $reloadedCard->getBalance())->toBe(70.00);
    });
});

describe('Refund Flow - Order Fully Paid by Gift Card', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');

        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(150.00);
        $this->giftcard->setInitialBalance(150.00);
        $this->giftcard->save();

        // Order fully paid by gift card (grand_total = 0)
        $this->order = Mage::getModel('sales/order');
        $this->order->setIncrementId('REFUND-FULLGC-' . time() . '-' . mt_rand(1000, 9999));
        $this->order->setStoreId(1);
        $this->order->setStore(Mage::app()->getStore(1));
        $this->order->setBaseCurrencyCode('USD');
        $this->order->setOrderCurrencyCode('USD');
        $this->order->setBaseToOrderRate(1.0);
        $this->order->setBaseSubtotal(100.00);
        $this->order->setSubtotal(100.00);
        $this->order->setBaseShippingAmount(10.00);
        $this->order->setShippingAmount(10.00);
        $this->order->setBaseTaxAmount(10.00);
        $this->order->setTaxAmount(10.00);
        $this->order->setBaseGrandTotal(0.00); // Fully paid by gift card
        $this->order->setGrandTotal(0.00);
        $this->order->setBaseGiftcardAmount(120.00);
        $this->order->setGiftcardAmount(120.00);
        $this->order->setGiftcardCodes(json_encode([$this->giftcard->getCode() => 120.00]));
        $this->order->save();

        // Simulate card was used
        $this->giftcard->setBalance(30.00); // 150 - 120 = 30 remaining
        $this->giftcard->save();
    });

    test('restores correct amount for fully gift-card-paid order', function () {
        // Full refund of order that was fully paid by gift card
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-120.00);
        $creditmemo->setGiftcardAmount(-120.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());

        // Balance should be restored: 30 + 120 = 150 (original balance)
        expect((float) $reloadedCard->getBalance())->toBe(150.00);
    });

    test('handles partial refund of fully gift-card-paid order', function () {
        // Partial refund: $60 of $120 gift card amount
        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($this->order);
        $creditmemo->setBaseGiftcardAmount(-60.00);
        $creditmemo->setGiftcardAmount(-60.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());

        // Balance should be: 30 + 60 = 90
        expect((float) $reloadedCard->getBalance())->toBe(90.00);
    });
});

describe('Refund Flow - Expired Card Expiration Extension', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');
    });

    test('extends expiration when refunding to expired gift card', function () {
        // Create an expired gift card
        $pastDate = (new DateTime('now', new DateTimeZone('UTC')))->modify('-10 days');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($pastDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-EXPIRED-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(100.00);
        $order->setGiftcardAmount(100.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 100.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-50.00);
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be restored
        expect((float) $reloadedCard->getBalance())->toBe(50.00);

        // Status should be active
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Expiration should be extended (default 30 days from now)
        $newExpiresAt = new DateTime($reloadedCard->getExpiresAt(), new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expectedMin = (clone $now)->modify('+29 days'); // Allow 1 day tolerance
        $expectedMax = (clone $now)->modify('+31 days');

        expect($newExpiresAt >= $expectedMin)->toBeTrue();
        expect($newExpiresAt <= $expectedMax)->toBeTrue();
    });

    test('extends expiration when card has passed expiration date but status not yet updated', function () {
        // Card with past expiration but still ACTIVE status (cron hasn't run yet)
        $pastDate = (new DateTime('now', new DateTimeZone('UTC')))->modify('-5 days');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE); // Not yet marked expired
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(20.00); // Still has some balance
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($pastDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-PASTEXP-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(80.00);
        $order->setGiftcardAmount(80.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 80.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-40.00);
        $creditmemo->setGiftcardAmount(-40.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be increased
        expect((float) $reloadedCard->getBalance())->toBe(60.00); // 20 + 40

        // Status should be active
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Expiration should be extended since the date was in the past
        $newExpiresAt = new DateTime($reloadedCard->getExpiresAt(), new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));

        expect($newExpiresAt > $now)->toBeTrue();
    });

    test('extends expiration when card expires within extension period', function () {
        // Card expires in 10 days, but extension is 30 days - should extend to 30 days
        $soonDate = (new DateTime('now', new DateTimeZone('UTC')))->modify('+10 days');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($soonDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-SOON-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(100.00);
        $order->setGiftcardAmount(100.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 100.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-100.00);
        $creditmemo->setGiftcardAmount(-100.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be restored
        expect((float) $reloadedCard->getBalance())->toBe(100.00);

        // Expiration should be extended to ~30 days from now (not the original 10)
        $newExpiresAt = new DateTime($reloadedCard->getExpiresAt(), new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expectedMin = (clone $now)->modify('+29 days');
        $expectedMax = (clone $now)->modify('+31 days');

        expect($newExpiresAt >= $expectedMin)->toBeTrue();
        expect($newExpiresAt <= $expectedMax)->toBeTrue();
    });

    test('does not extend expiration when card has more time than extension period', function () {
        // Card expires in 60 days, extension is 30 days - should NOT extend
        $futureDate = (new DateTime('now', new DateTimeZone('UTC')))->modify('+60 days');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($futureDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $originalExpiresAt = $giftcard->getExpiresAt();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-FUTURE-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(100.00);
        $order->setGiftcardAmount(100.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 100.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-100.00);
        $creditmemo->setGiftcardAmount(-100.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be restored
        expect((float) $reloadedCard->getBalance())->toBe(100.00);

        // Expiration should NOT be changed (still 60 days out)
        expect($reloadedCard->getExpiresAt())->toBe($originalExpiresAt);
    });

    test('records expiration extension in history comment', function () {
        $pastDate = (new DateTime('now', new DateTimeZone('UTC')))->modify('-10 days');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->setExpiresAt($pastDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-HISTORY-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 50.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-50.00);
        $creditmemo->setGiftcardAmount(-50.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        // Check history entry mentions expiration extension
        $latestHistory = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $giftcard->getId())
            ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
            ->setOrder('created_at', 'DESC')
            ->getFirstItem();

        expect($latestHistory->getComment())->toContain('expiration extended to 30 days from now');
    });

    test('handles card with no expiration date', function () {
        // Card without expiration (never expires)
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(75.00);
        $giftcard->setExpiresAt(null); // No expiration
        $giftcard->save();

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('REFUND-NOEXP-' . time() . '-' . mt_rand(1000, 9999));
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseGiftcardAmount(75.00);
        $order->setGiftcardAmount(75.00);
        $order->setGiftcardCodes(json_encode([$giftcard->getCode() => 75.00]));
        $order->save();

        $creditmemo = Mage::getModel('sales/order_creditmemo');
        $creditmemo->setOrder($order);
        $creditmemo->setBaseGiftcardAmount(-75.00);
        $creditmemo->setGiftcardAmount(-75.00);
        $creditmemo->save();

        $event = new Maho\Event();
        $event->setCreditmemo($creditmemo);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $this->observer->refundGiftcardBalance($eventObserver);

        $reloadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        // Balance should be restored
        expect((float) $reloadedCard->getBalance())->toBe(75.00);

        // Status should be active
        expect($reloadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Expiration should still be null
        expect($reloadedCard->getExpiresAt())->toBeNull();
    });
});
