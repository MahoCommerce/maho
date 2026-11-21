<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

class PaypalCapturePlacementOfflineMethod extends Maho_Paypal_Model_Method_StandardCheckout
{
    #[\Override]
    public function isAvailable($quote = null): bool
    {
        return true;
    }

    #[\Override]
    protected function _getApiClient(): Maho_Paypal_Model_Api_Client
    {
        throw new RuntimeException('Expected checkout to reuse the PayPal result.');
    }
}

function placePaypalCheckoutOrder(string $intent, array $payments): Mage_Sales_Model_Order
{
    Mage::getConfig()->setNode('global/models/paypal/rewrite/method_standardCheckout', PaypalCapturePlacementOfflineMethod::class);
    $store = Mage::app()->getStore(1);
    $store->setConfig('payment/paypal_standard_checkout/payment_action', $intent);
    $store->setConfig('sales_email/order/enabled', '0');

    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('TEST-PAYPAL-' . bin2hex(random_bytes(6)))->setName('Example service')->setPrice(100.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
    $product->save();

    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsActive(true)
        ->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST)
        ->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $quote->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId()), 1);
    $quote->getBillingAddress()->setCountryId('US')->setRegionId(12)->setPostcode('90210')
        ->setFirstname('Example')->setLastname('Customer')->setStreet('1 Example Street')->setCity('Los Angeles')
        ->setTelephone('5550100')->setEmail('paypal-checkout@example.invalid');
    $quote->collectTotals()->reserveOrderId()->save();
    $result = [
        'id' => 'ORDER-' . bin2hex(random_bytes(6)),
        'status' => 'COMPLETED',
        'purchase_units' => [[
            'invoice_id' => $quote->getReservedOrderId(),
            'amount' => ['currency_code' => 'USD', 'value' => '100.00'],
            'payments' => $payments,
        ]],
    ];
    Mage::helper('paypal')->placeOrderFromPaypalResult($quote, $result, Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT, $intent);

    return Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
}

function notifyPaypalCaptureCompleted(Mage_Sales_Model_Order $order, string $captureId, string $currency, string $value): void
{
    Mage::getModel('paypal/webhook_processor')->process([
        'id' => 'EVENT-' . bin2hex(random_bytes(6)),
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => $captureId,
            'invoice_id' => $order->getIncrementId(),
            'status' => 'COMPLETED',
            'amount' => ['currency_code' => $currency, 'value' => $value],
        ],
    ]);
}

it('accounts for a PayPal checkout capture only when its full amount is confirmed', function (array $capture, bool $paid): void {
    $captureId = 'CAPTURE-' . bin2hex(random_bytes(6));
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $order = placePaypalCheckoutOrder('capture', ['captures' => [$capture + ['id' => $captureId]]]);
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        expect($order->getId())->not->toBeNull()
            ->and($order->getInvoiceCollection()->count())->toBe(1)
            ->and($invoice->getTransactionId())->toBe($captureId)
            ->and((int) $invoice->getState())->toBe($paid ? Mage_Sales_Model_Order_Invoice::STATE_PAID : Mage_Sales_Model_Order_Invoice::STATE_OPEN)
            ->and((float) $order->getBaseTotalPaid())->toBe($paid ? 100.0 : 0.0)
            ->and((float) $order->getPayment()->getBaseAmountPaidOnline())->toBe($paid ? 100.0 : 0.0);

        if ($capture['status'] === 'PENDING') {
            notifyPaypalCaptureCompleted($order, $captureId, 'EUR', '100.00');
            notifyPaypalCaptureCompleted($order, $captureId, 'USD', '90.00');
            $unchanged = Mage::getModel('sales/order')->load($order->getId());
            expect((int) $unchanged->getInvoiceCollection()->getFirstItem()->getState())->toBe(Mage_Sales_Model_Order_Invoice::STATE_OPEN)
                ->and((float) $unchanged->getPayment()->getBaseAmountPaidOnline())->toBe(0.0);
        }

        if ($paid || $capture['status'] === 'PENDING') {
            $captureTransaction = $order->getPayment()->getTransaction($captureId);
            notifyPaypalCaptureCompleted($order, $captureId, 'USD', '100.00');
            notifyPaypalCaptureCompleted($order, $captureId, 'USD', '100.00');
            $confirmed = Mage::getModel('sales/order')->load($order->getId());
            $confirmedInvoice = $confirmed->getInvoiceCollection()->getFirstItem();
            expect($confirmed->getInvoiceCollection()->count())->toBe(1)
                ->and($confirmedInvoice->getId())->toBe($invoice->getId())
                ->and((int) $confirmedInvoice->getState())->toBe(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                ->and((float) $confirmed->getBaseTotalPaid())->toBe(100.0)
                ->and((float) $confirmed->getPayment()->getBaseAmountPaidOnline())->toBe(100.0)
                ->and($confirmed->getPayment()->getTransaction($captureId)->getId())->toBe($captureTransaction->getId());
        }
    } finally {
        $connection->rollBack();
    }
})->with([
    'completed full capture' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '100.00']], true],
    'pending full capture' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'USD', 'value' => '100.00']], false],
    'completed partial capture' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '90.00']], false],
    'completed capture in another currency' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '100.00']], false],
]);

it('registers a new confirmed capture when checkout saved only an authorization', function (): void {
    $captureId = 'CAPTURE-' . bin2hex(random_bytes(6));
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $order = placePaypalCheckoutOrder('authorize', ['authorizations' => [['id' => 'AUTH-' . $captureId]]]);
        expect($order->getId())->not->toBeNull()
            ->and($order->getInvoiceCollection()->count())->toBe(0);

        notifyPaypalCaptureCompleted($order, $captureId, 'USD', '100.00');
        notifyPaypalCaptureCompleted($order, $captureId, 'USD', '100.00');

        $confirmed = Mage::getModel('sales/order')->load($order->getId());
        $invoice = $confirmed->getInvoiceCollection()->getFirstItem();
        expect($confirmed->getInvoiceCollection()->count())->toBe(1)
            ->and($invoice->getTransactionId())->toBe($captureId)
            ->and((int) $invoice->getState())->toBe(Mage_Sales_Model_Order_Invoice::STATE_PAID)
            ->and((float) $confirmed->getBaseTotalPaid())->toBe(100.0)
            ->and((float) $confirmed->getPayment()->getBaseAmountPaidOnline())->toBe(100.0);
    } finally {
        $connection->rollBack();
    }
});

it('pays the placement invoice only when PayPal confirms the full capture', function (
    array $capture,
    bool $paid,
    float $orderTotal = 100.0,
    string $currency = 'USD',
    ?bool $paidAfterNotification = null,
): void {
    $suffix = bin2hex(random_bytes(6));
    $paypalOrderId = 'ORDER-' . $suffix;
    $captureId = 'CAPTURE-' . $suffix;
    $incrementId = 'TEST-' . $suffix;
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setIncrementId($incrementId)
        ->setCustomerIsGuest()
        ->setCustomerEmail('paypal-invoice@example.invalid')
        ->setBaseCurrencyCode($currency)->setOrderCurrencyCode($currency)->setGlobalCurrencyCode($currency)
        ->setBaseToOrderRate(1)->setBaseToGlobalRate(1)
        ->setSubtotal($orderTotal)->setBaseSubtotal($orderTotal)
        ->setGrandTotal($orderTotal)->setBaseGrandTotal($orderTotal)
        ->setTotalQtyOrdered(1);
    $order->setBillingAddress(Mage::getModel('sales/order_address')->setCountryId('US'));

    $item = Mage::getModel('sales/order_item');
    $item->setStoreId(1)->setProductType('simple')->setSku('TEST-ITEM')->setName('Test item')
        ->setQtyOrdered(1)->setPrice($orderTotal)->setBasePrice($orderTotal)->setOriginalPrice($orderTotal)
        ->setRowTotal($orderTotal)->setBaseRowTotal($orderTotal);
    $order->addItem($item);

    $payment = Mage::getModel('sales/order_payment')->setMethod(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
    $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
    $order->setPayment($payment);
    $order->getStore()->setConfig('payment/paypal_standard_checkout/payment_action', 'capture');
    $paypalValue = in_array($currency, ['HUF', 'JPY', 'TWD'], true) ? '100' : '100.00';

    $client = $this->createMock(Maho_Paypal_Model_Api_Client::class);
    $client->expects($this->once())->method('getOrder')->with($paypalOrderId)->willReturn([
        'id' => $paypalOrderId,
        'status' => 'APPROVED',
        'purchase_units' => [['invoice_id' => $incrementId, 'amount' => ['currency_code' => $currency, 'value' => $paypalValue]]],
    ]);
    $client->expects($this->once())->method('captureOrder')->with($paypalOrderId)->willReturn([
        'id' => $paypalOrderId,
        'status' => 'COMPLETED',
        'purchase_units' => [['payments' => ['captures' => [$capture + ['id' => $captureId]]]]],
    ]);
    $client->expects($this->never())->method('captureAuthorization');
    $payment->getMethodInstance()->setApiClient($client);

    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $order->place()->save();
        $saved = Mage::getModel('sales/order')->load($order->getId());
        $invoices = $saved->getInvoiceCollection();
        $invoice = $invoices->getFirstItem();

        expect($invoices->count())->toBe(1)
            ->and($invoice->getTransactionId())->toBe($captureId)
            ->and((int) $invoice->getState())->toBe($paid ? Mage_Sales_Model_Order_Invoice::STATE_PAID : Mage_Sales_Model_Order_Invoice::STATE_OPEN)
            ->and($invoice->canRefund())->toBe($paid)
            ->and((float) $saved->getBaseTotalInvoiced())->toBe($orderTotal)
            ->and((float) $saved->getBaseTotalPaid())->toBe($paid ? $orderTotal : 0.0)
            ->and((float) $saved->getPayment()->getBaseAmountPaidOnline())->toBe($paid ? 100.0 : 0.0);

        if ($paid) {
            $saved->getPayment()->setTransactionId($captureId)->setIsTransactionClosed(true);
            $saved->getPayment()->registerCaptureNotification(100.0);
            $saved->save();
            $saved = Mage::getModel('sales/order')->load($order->getId());
            expect($saved->getInvoiceCollection()->count())->toBe(1)
                ->and((float) $saved->getBaseTotalPaid())->toBe($orderTotal)
                ->and((float) $saved->getPayment()->getBaseAmountPaidOnline())->toBe(100.0);
        }

        if ($paidAfterNotification !== null) {
            notifyPaypalCaptureCompleted($saved, $captureId, $currency, $paypalValue);
            notifyPaypalCaptureCompleted($saved, $captureId, $currency, $paypalValue);
            $confirmed = Mage::getModel('sales/order')->load($saved->getId());
            $confirmedInvoice = $confirmed->getInvoiceCollection()->getFirstItem();
            expect($confirmed->getInvoiceCollection()->count())->toBe(1)
                ->and($confirmedInvoice->getId())->toBe($invoice->getId())
                ->and((int) $confirmedInvoice->getState())->toBe($paidAfterNotification ? Mage_Sales_Model_Order_Invoice::STATE_PAID : Mage_Sales_Model_Order_Invoice::STATE_OPEN)
                ->and((float) $confirmed->getBaseTotalPaid())->toBe($paidAfterNotification ? $orderTotal : 0.0)
                ->and((float) $confirmed->getPayment()->getBaseAmountPaidOnline())->toBe($paidAfterNotification ? 100.0 : 0.0);
        }
    } finally {
        $connection->rollBack();
    }
})->with([
    'completed full capture' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '100.00']], true],
    'completed full capture rounded by PayPal' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '100.00']], true, 100.004],
    'pending capture' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'USD', 'value' => '100.00']], false],
    'completed partial capture' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '90.00']], false],
    'completed capture in another currency' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '100.00']], false],
    'completed full capture in HUF' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'HUF', 'value' => '100']], true, 100.0, 'HUF'],
    'pending full capture in HUF' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'HUF', 'value' => '100']], false, 100.0, 'HUF', true],
    'completed capture for fractional HUF order' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'HUF', 'value' => '100']], false, 100.004, 'HUF'],
    'pending capture for fractional HUF order' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'HUF', 'value' => '100']], false, 100.004, 'HUF', false],
    'completed full capture in JPY' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'JPY', 'value' => '100']], true, 100.0, 'JPY'],
    'pending full capture in JPY' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'JPY', 'value' => '100']], false, 100.0, 'JPY', true],
    'completed capture for fractional JPY order' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'JPY', 'value' => '100']], false, 100.004, 'JPY'],
    'pending capture for fractional JPY order' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'JPY', 'value' => '100']], false, 100.004, 'JPY', false],
    'completed full capture in TWD' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'TWD', 'value' => '100']], true, 100.0, 'TWD'],
    'pending full capture in TWD' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'TWD', 'value' => '100']], false, 100.0, 'TWD', true],
    'completed capture for fractional TWD order' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'TWD', 'value' => '100']], false, 100.004, 'TWD'],
    'pending capture for fractional TWD order' => [['status' => 'PENDING', 'amount' => ['currency_code' => 'TWD', 'value' => '100']], false, 100.004, 'TWD', false],
]);
