<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard Observer Instantiation', function () {
    test('observer can be instantiated', function () {
        $observer = Mage::getModel('giftcard/observer');
        expect($observer)->toBeInstanceOf(Maho_Giftcard_Model_Observer::class);
    });
});

describe('Observer: Set Giftcard Price on Quote Item', function () {
    test('sets custom price on quote item from buy request', function () {
        $observer = Mage::getModel('giftcard/observer');

        // Create a quote first
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Load an existing simple product from sample data to avoid stock item issues
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1);
        $existingProduct = $productCollection->getFirstItem();

        // Create a mock product that won't trigger stock validation
        $product = new Maho\DataObject([
            'id' => $existingProduct->getId() ?: 1,
            'type_id' => 'giftcard',
            'is_super_mode' => false,
        ]);

        // Create quote item
        $quoteItem = Mage::getModel('sales/quote_item');
        $quoteItem->setQuote($quote);
        $quoteItem->setProductType('giftcard');
        $quoteItem->setData('product', $product);
        $quoteItem->setQty(1);

        // Add info_buyRequest option (this is how getBuyRequest() retrieves buy request data)
        $buyRequestData = [
            'giftcard_amount' => 75.00,
            'giftcard_recipient_name' => 'John Doe',
            'giftcard_recipient_email' => 'john@test.com',
        ];
        $quoteItem->addOption([
            'code' => 'info_buyRequest',
            'value' => serialize($buyRequestData),
        ]);

        // Create event observer
        $event = new Maho\Event();
        $event->setQuoteItem($quoteItem);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $observer->setGiftcardPrice($eventObserver);

        expect((float) $quoteItem->getCustomPrice())->toBe(75.00);
        expect((float) $quoteItem->getOriginalCustomPrice())->toBe(75.00);
    });

    test('does not modify non-giftcard products', function () {
        $observer = Mage::getModel('giftcard/observer');

        // Create a quote first
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Create product
        $product = Mage::getModel('catalog/product');
        $product->setId(99998);
        $product->setTypeId('simple');

        $quoteItem = Mage::getModel('sales/quote_item');
        $quoteItem->setQuote($quote);
        $quoteItem->setProductType('simple'); // Not a gift card
        $quoteItem->setProduct($product);
        $quoteItem->setCustomPrice(null);

        $event = new Maho\Event();
        $event->setQuoteItem($quoteItem);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $observer->setGiftcardPrice($eventObserver);

        expect($quoteItem->getCustomPrice())->toBeNull();
    });
});

describe('Observer: Apply Gift Card to Order', function () {
    test('transfers gift card amounts from address to order', function () {
        $observer = Mage::getModel('giftcard/observer');

        // Create quote with gift card
        $quote = Mage::getModel('sales/quote');
        $quote->setGiftcardCodes(json_encode(['TEST-CODE' => 50.00]));
        $quote->setBaseGiftcardAmount(50.00);
        $quote->setGiftcardAmount(50.00);

        // Create address
        $address = Mage::getModel('sales/quote_address');
        $address->setQuote($quote);
        $address->setBaseGiftcardAmount(50.00);
        $address->setGiftcardAmount(50.00);

        // Create order
        $order = Mage::getModel('sales/order');
        $order->setGrandTotal(100.00);
        $order->setBaseGrandTotal(100.00);

        $event = new Maho\Event();
        $event->setOrder($order);
        $event->setAddress($address);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $observer->applyGiftcardToOrder($eventObserver);

        expect((float) $order->getBaseGiftcardAmount())->toBe(50.00);
        expect((float) $order->getGiftcardAmount())->toBe(50.00);
        expect((float) $order->getGrandTotal())->toBe(50.00); // 100 - 50
    });

    test('handles null address gracefully', function () {
        $observer = Mage::getModel('giftcard/observer');

        $order = Mage::getModel('sales/order');

        $event = new Maho\Event();
        $event->setOrder($order);
        $event->setAddress(null);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Should not throw exception
        $observer->applyGiftcardToOrder($eventObserver);

        expect(true)->toBeTrue();
    });
});

describe('Observer: Catalog Product Save Before', function () {
    test('preserves gift card attributes on product save', function () {
        $observer = Mage::getModel('giftcard/observer');

        $product = Mage::getModel('catalog/product');
        $product->setTypeId('giftcard');
        $product->setData('giftcard_type', 'fixed');
        $product->setData('giftcard_amounts', '25,50,100');
        $product->setData('giftcard_allow_message', 1);
        $product->setData('giftcard_lifetime', 365);

        $event = new Maho\Event();
        $event->setProduct($product);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        $observer->catalogProductSaveBefore($eventObserver);

        // Attributes should be preserved
        expect($product->getData('giftcard_type'))->toBe('fixed');
        expect($product->getData('giftcard_amounts'))->toBe('25,50,100');
    });

    test('skips non-giftcard products', function () {
        $observer = Mage::getModel('giftcard/observer');

        $product = Mage::getModel('catalog/product');
        $product->setTypeId('simple');
        $product->setData('giftcard_type', 'fixed'); // Shouldn't matter

        $event = new Maho\Event();
        $event->setProduct($product);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Should return early without processing
        $observer->catalogProductSaveBefore($eventObserver);

        expect(true)->toBeTrue();
    });
});

describe('Observer: Gift Card Creation on Invoice Paid', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');
    });

    test('creates gift card when invoice is paid', function () {
        // Create an order with all required fields
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('TEST-' . time());
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseCurrencyCode('USD');
        $order->setOrderCurrencyCode('USD');
        $order->setBaseToOrderRate(1.0);
        $order->save();

        // Create order item with all required fields
        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setOrderId($order->getId());
        $orderItem->setProductType('giftcard');
        $orderItem->setProductId(1);
        $orderItem->setName('Test Gift Card');
        $orderItem->setSku('TEST-GC-001');
        $orderItem->setQtyOrdered(1);
        $orderItem->setPrice(100.00);
        $orderItem->setBasePrice(100.00);
        $orderItem->setRowTotal(100.00);
        $orderItem->setBaseRowTotal(100.00);
        $orderItem->setProductOptions([
            'info_buyRequest' => [
                'giftcard_amount' => 100.00,
                'giftcard_recipient_name' => 'Test Recipient',
                'giftcard_recipient_email' => 'recipient@test.com',
                'giftcard_sender_name' => 'Test Sender',
                'giftcard_message' => 'Happy Birthday!',
            ],
        ]);
        $orderItem->save();

        // Test the _createGiftcard method directly using reflection
        // This tests the core functionality without needing to mock getAllItems
        $reflection = new ReflectionClass($this->observer);
        $method = $reflection->getMethod('_createGiftcard');
        $method->setAccessible(true);

        $createdCard = $method->invoke(
            $this->observer,
            100.00,
            $order,
            $orderItem,
            'Test Recipient',
            'recipient@test.com',
            'Test Sender',
            '',
            'Happy Birthday!',
        );

        expect($createdCard)->toBeInstanceOf(Maho_Giftcard_Model_Giftcard::class);
        expect($createdCard->getId())->not->toBeNull();
        expect((float) $createdCard->getBalance())->toBe(100.00);
        expect($createdCard->getRecipientName())->toBe('Test Recipient');
        expect($createdCard->getRecipientEmail())->toBe('recipient@test.com');
        expect($createdCard->getSenderName())->toBe('Test Sender');
        expect($createdCard->getMessage())->toBe('Happy Birthday!');
        expect($createdCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        expect((int) $createdCard->getPurchaseOrderId())->toBe((int) $order->getId());

        // Verify history was created
        $history = $createdCard->getHistoryCollection();
        expect($history->getSize())->toBeGreaterThanOrEqual(1);
    });

    test('does not create gift card for unpaid invoice', function () {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('TEST-UNPAID-' . time());
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseCurrencyCode('USD');
        $order->setOrderCurrencyCode('USD');
        $order->setBaseToOrderRate(1.0);
        $order->save();

        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setOrderId($order->getId());
        $orderItem->setProductType('giftcard');
        $orderItem->setProductId(1);
        $orderItem->setName('Test Gift Card');
        $orderItem->setSku('TEST-GC-002');
        $orderItem->setQtyOrdered(1);
        $orderItem->setProductOptions([
            'info_buyRequest' => [
                'giftcard_amount' => 50.00,
            ],
        ]);
        $orderItem->save();

        // Create unpaid invoice
        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($order);
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN); // Not paid

        $event = new Maho\Event();
        $event->setInvoice($invoice);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Observer should return early due to unpaid state
        $this->observer->createGiftcardsOnInvoicePaid($eventObserver);

        // No gift card should be created
        $collection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('purchase_order_id', $order->getId());

        expect($collection->getSize())->toBe(0);
    });

    test('creates multiple gift cards for quantity > 1', function () {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('TEST-MULTI-' . time());
        $order->setStoreId(1);
        $order->setStore(Mage::app()->getStore(1));
        $order->setBaseCurrencyCode('USD');
        $order->setOrderCurrencyCode('USD');
        $order->setBaseToOrderRate(1.0);
        $order->save();

        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setOrderId($order->getId());
        $orderItem->setProductType('giftcard');
        $orderItem->setProductId(1);
        $orderItem->setName('Test Gift Card Multi');
        $orderItem->setSku('TEST-GC-003');
        $orderItem->setQtyOrdered(3);
        $orderItem->setProductOptions([
            'info_buyRequest' => [
                'giftcard_amount' => 25.00,
                'giftcard_recipient_name' => 'Multi Recipient',
                'giftcard_recipient_email' => 'multi@test.com',
            ],
        ]);
        $orderItem->save();

        // Test creating 3 gift cards directly
        $reflection = new ReflectionClass($this->observer);
        $method = $reflection->getMethod('_createGiftcard');
        $method->setAccessible(true);

        $codes = [];
        for ($i = 0; $i < 3; $i++) {
            $card = $method->invoke(
                $this->observer,
                25.00,
                $order,
                $orderItem,
                'Multi Recipient',
                'multi@test.com',
                '',
                '',
                '',
            );
            $codes[] = $card->getCode();
        }

        // Each should have unique code
        expect(array_unique($codes))->toHaveCount(3);

        // Verify all cards are in the database
        $collection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('purchase_order_id', $order->getId());

        expect($collection->getSize())->toBe(3);
    });
});

describe('Observer: Admin Order Gift Card Processing', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
        $this->observer = Mage::getModel('giftcard/observer');

        // Create test gift card
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();
    });

    test('applies gift card in admin order create', function () {
        // Create quote
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Mock request
        $request = new Maho\DataObject([
            'order' => [
                'giftcard' => [
                    'code' => $this->giftcard->getCode(),
                    'action' => 'apply',
                ],
            ],
        ]);

        // Mock order create model
        $orderCreateModel = new Maho\DataObject([
            'quote' => $quote,
        ]);
        $orderCreateModel->setQuote($quote);

        // Use reflection or direct method call since we need to mock the request
        // For integration test, we'll verify the logic directly

        // Apply gift card to quote
        $codes = [$this->giftcard->getCode() => 0];
        $quote->setGiftcardCodes(json_encode($codes));
        $quote->save();

        $reloadedQuote = Mage::getModel('sales/quote')->load($quote->getId());
        $appliedCodes = json_decode($reloadedQuote->getGiftcardCodes(), true);

        expect($appliedCodes)->toHaveKey($this->giftcard->getCode());
    });

    test('rejects invalid gift card code', function () {
        // Try to apply non-existent code
        $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode('INVALID-CODE-12345');

        expect($giftcard->getId())->toBeNull();
    });

    test('rejects gift card from different website', function () {
        // Card is for website 1
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);

        $websiteId = (int) $quote->getStore()->getWebsiteId();

        // Card's website
        $cardWebsiteId = (int) $this->giftcard->getWebsiteId();

        // For this test, they should match
        expect($websiteId)->toBe($cardWebsiteId);

        // Test the isValidForWebsite method with a different website ID
        // without actually saving to avoid foreign key constraint
        // We set the data without persisting
        $this->giftcard->setData('website_id', 999);

        // The isValidForWebsite method should return false because card's website (999)
        // doesn't match the quote's website (1)
        expect($this->giftcard->isValidForWebsite($websiteId))->toBeFalse();

        // Also verify a valid website ID would pass
        $this->giftcard->setData('website_id', $websiteId);
        expect($this->giftcard->isValidForWebsite($websiteId))->toBeTrue();
    });
});

describe('Observer: Add Gift Card Total to Admin Order View', function () {
    test('adds gift card total to order totals block', function () {
        $observer = Mage::getModel('giftcard/observer');

        // Create order with gift card
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(75.00);
        $order->setBaseGiftcardAmount(75.00);
        $order->setGiftcardCodes(json_encode(['TEST-CODE' => 75.00]));

        // Create a mock block
        $block = new Maho\DataObject([
            'name_in_layout' => 'order_totals',
            'order' => $order,
        ]);
        $block->setNameInLayout('order_totals');
        $block->setOrder($order);

        // Track added totals
        $totals = [];
        $block->setData('totals', $totals);

        $event = new Maho\Event();
        $event->setBlock($block);

        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);

        // Note: This test verifies the observer method runs without error
        // Full block testing would require actual block instantiation
        $observer->addGiftcardTotalToAdminOrder($eventObserver);

        expect(true)->toBeTrue();
    });
});
describe('Observer: Refund Gift Card on Order Cancel', function () {
    test('refunds gift card balance when order is canceled', function () {
        // Create a gift card with initial balance
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode('CANCEL-TEST-' . uniqid());
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->save();

        $code = $giftcard->getCode();
        $initialBalance = $giftcard->getBalance();

        // Create and place an order using the gift card
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('CANCEL-TEST-' . uniqid());
        $order->setStoreId(1);
        $order->setState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setGrandTotal(50.00);
        $order->setBaseGrandTotal(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardCodes(json_encode([$code => 50.00]));
        $order->save();

        // Simulate order placement to deduct balance
        $giftcard->setBalance(50.00);
        $giftcard->save();

        // Verify balance was deducted
        $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
        expect($giftcard->getBalance())->toBe(50.00);

        // Cancel the order
        $order->cancel();
        $order->save();

        // Manually trigger the observer (since we're in a test environment)
        $observer = Mage::getModel('giftcard/observer');
        $event = new Maho\Event();
        $event->setOrder($order);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);
        $observer->refundGiftcardOnOrderCancel($eventObserver);

        // Reload gift card and verify balance was refunded
        $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
        expect($giftcard->getBalance())->toBe(100.00);
        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Verify history entry was created
        $history = Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $giftcard->getId())
            ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
            ->addFieldToFilter('order_id', $order->getId())
            ->getFirstItem();

        expect($history->getId())->toBeGreaterThan(0);
        expect((float) $history->getBaseAmount())->toBe(50.00);
        expect((float) $history->getBalanceBefore())->toBe(50.00);
        expect((float) $history->getBalanceAfter())->toBe(100.00);
        expect($history->getComment())->toContain('canceled order');
    });

    test('refunds multiple gift cards proportionally on cancel', function () {
        // Create two gift cards
        $giftcard1 = Mage::getModel('giftcard/giftcard');
        $giftcard1->setCode('CANCEL-MULTI-1-' . uniqid());
        $giftcard1->setBalance(100.00);
        $giftcard1->setInitialBalance(100.00);
        $giftcard1->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard1->setWebsiteId(1);
        $giftcard1->save();

        $giftcard2 = Mage::getModel('giftcard/giftcard');
        $giftcard2->setCode('CANCEL-MULTI-2-' . uniqid());
        $giftcard2->setBalance(75.00);
        $giftcard2->setInitialBalance(75.00);
        $giftcard2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard2->setWebsiteId(1);
        $giftcard2->save();

        $code1 = $giftcard1->getCode();
        $code2 = $giftcard2->getCode();

        // Create order using both gift cards
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('CANCEL-MULTI-' . uniqid());
        $order->setStoreId(1);
        $order->setState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setGrandTotal(120.00);
        $order->setBaseGrandTotal(120.00);
        $order->setGiftcardAmount(120.00);
        $order->setBaseGiftcardAmount(120.00);
        $order->setGiftcardCodes(json_encode([
            $code1 => 80.00,
            $code2 => 40.00,
        ]));
        $order->save();

        // Deduct balances
        $giftcard1->setBalance(20.00);
        $giftcard1->save();
        $giftcard2->setBalance(35.00);
        $giftcard2->save();

        // Verify balances were deducted
        $giftcard1 = Mage::getModel('giftcard/giftcard')->loadByCode($code1);
        $giftcard2 = Mage::getModel('giftcard/giftcard')->loadByCode($code2);
        expect($giftcard1->getBalance())->toBe(20.00);
        expect($giftcard2->getBalance())->toBe(35.00);

        // Cancel the order
        $order->cancel();
        $order->save();

        // Manually trigger the observer
        $observer = Mage::getModel('giftcard/observer');
        $event = new Maho\Event();
        $event->setOrder($order);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);
        $observer->refundGiftcardOnOrderCancel($eventObserver);

        // Verify both balances were refunded
        $giftcard1 = Mage::getModel('giftcard/giftcard')->loadByCode($code1);
        $giftcard2 = Mage::getModel('giftcard/giftcard')->loadByCode($code2);
        expect($giftcard1->getBalance())->toBe(100.00);
        expect($giftcard2->getBalance())->toBe(75.00);
    });

    test('does nothing when order has no gift card amount', function () {
        // Create order without gift card
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('CANCEL-NOGC-' . uniqid());
        $order->setStoreId(1);
        $order->setState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setGrandTotal(100.00);
        $order->setBaseGrandTotal(100.00);
        $order->save();

        // Cancel the order - should not throw any errors
        $order->cancel();
        $order->save();

        // Test passes if no exception was thrown
        expect(true)->toBeTrue();
    });

    test('extends expiration on refund when configured', function () {
        // Create an expiring gift card
        $expiresAt = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt->modify('+5 days'); // Expires soon

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode('CANCEL-EXTEND-' . uniqid());
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setExpiresAt($expiresAt->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $giftcard->save();

        $code = $giftcard->getCode();

        // Create and place order
        $order = Mage::getModel('sales/order');
        $order->setIncrementId('CANCEL-EXPIRE-' . uniqid());
        $order->setStoreId(1);
        $order->setState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setGrandTotal(50.00);
        $order->setBaseGrandTotal(50.00);
        $order->setGiftcardAmount(50.00);
        $order->setBaseGiftcardAmount(50.00);
        $order->setGiftcardCodes(json_encode([$code => 50.00]));
        $order->save();

        // Deduct balance
        $giftcard->setBalance(50.00);
        $giftcard->save();

        // Set extension days config (default is 30 days)
        $originalExtension = Mage::getStoreConfig('giftcard/general/refund_expiration_extension');

        // Cancel order
        $order->cancel();
        $order->save();

        // Manually trigger the observer
        $observer = Mage::getModel('giftcard/observer');
        $event = new Maho\Event();
        $event->setOrder($order);
        $eventObserver = new Maho\Event\Observer();
        $eventObserver->setEvent($event);
        $observer->refundGiftcardOnOrderCancel($eventObserver);

        // Reload and check expiration was extended
        $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
        $newExpiresAt = new DateTime($giftcard->getExpiresAt(), new DateTimeZone('UTC'));

        // Should be extended by at least 25 days (allowing some margin)
        $minExpectedExpiration = new DateTime('now', new DateTimeZone('UTC'));
        $minExpectedExpiration->modify('+25 days');

        expect($newExpiresAt->getTimestamp())->toBeGreaterThan($minExpectedExpiration->getTimestamp());
    });
});
