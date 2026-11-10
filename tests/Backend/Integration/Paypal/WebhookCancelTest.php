<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

/**
 * The PayPal webhooks cancel an order through Mage_Sales_Service_Order::cancel(), which reads the order again under
 * the order lock (#1348).
 */

// One item of 100, not invoiced, so the order can be canceled.
function webhookCancelOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('WEBHOOK-CANCEL-' . uniqid())
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_NEW)->setStatus('pending')
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setBaseToOrderRate(1.0)
        ->setSubtotal(100.0)->setBaseSubtotal(100.0)
        ->setGrandTotal(100.0)->setBaseGrandTotal(100.0);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'sku' => 'webhook-cancel', 'product_type' => 'simple', 'qty_ordered' => 1,
        'price' => 100, 'base_price' => 100, 'row_total' => 100, 'base_row_total' => 100,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

/**
 * @return list<string>
 */
function webhookCancelComments(Mage_Sales_Model_Order $order): array
{
    $comments = [];
    foreach (Mage::getModel('sales/order')->load($order->getId())->getStatusHistoryCollection(true) as $history) {
        $comments[] = (string) $history->getComment();
    }
    return $comments;
}

beforeEach(function (): void {
    $this->order = webhookCancelOrder();
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    Mage::getModel('sales/order')->load($this->order->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('cancels the order of a declined capture, and records the event', function (): void {
    Mage::getModel('paypal/webhook_handler_captureDeclined')->handle([
        'resource' => ['id' => 'CAPTURE-1', 'invoice_id' => $this->order->getIncrementId(), 'status_details' => ['reason' => 'TEST']],
    ]);

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    expect($reloaded->getState())->toBe(Mage_Sales_Model_Order::STATE_CANCELED)
        ->and(webhookCancelComments($reloaded))->toContain('PayPal capture declined (TEST): CAPTURE-1');
});

it('keeps an order that an admin put on hold after the webhook read it, and records the event', function (): void {
    $stale = Mage::getModel('sales/order')->load($this->order->getId());
    Mage::getModel('sales/order')->load($this->order->getId())->hold()->save();
    $handler = new class ($stale) extends Maho_Paypal_Model_Webhook_Handler_AuthorizationVoided {
        public function __construct(private Mage_Sales_Model_Order $stale) {}

        #[\Override]
        protected function _findOrder(array $payload): ?Mage_Sales_Model_Order
        {
            return $this->stale;
        }
    };

    $handler->handle(['resource' => ['id' => 'AUTH-1']]);

    $reloaded = Mage::getModel('sales/order')->load($this->order->getId());
    expect($reloaded->getState())->toBe(Mage_Sales_Model_Order::STATE_HOLDED)
        ->and(webhookCancelComments($reloaded))->toContain('PayPal authorization voided: AUTH-1');
});
