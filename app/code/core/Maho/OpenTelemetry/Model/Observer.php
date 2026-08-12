<?php

/**
 * Records commerce lifecycle moments as span events on the active trace, giving shops business-level observability alongside the infrastructure spans.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

class Maho_OpenTelemetry_Model_Observer
{
    /**
     * Record the order placement on the trace: totals and payment method, but
     * never customer PII (no name, email or address)
     */
    #[\Maho\Config\Observer('sales_order_place_after')]
    public function addOrderPlacedEvent(\Maho\Event\Observer $observer): void
    {
        $tracer = Mage::getTracer();
        if (!$tracer?->isEnabled()) {
            return;
        }
        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order instanceof Mage_Sales_Model_Order) {
                return;
            }
            $tracer->getActiveSpan()?->addEvent('maho.order.placed', [
                'maho.order.increment_id' => (string) $order->getIncrementId(),
                'maho.order.grand_total' => (float) $order->getGrandTotal(),
                'maho.order.currency' => (string) $order->getOrderCurrencyCode(),
                'maho.order.items_count' => (int) $order->getTotalItemCount(),
                'maho.payment.method' => (string) ($order->getPayment()?->getMethod() ?? ''),
            ]);
            $tracer->addCounter('maho.orders', 1, [
                'maho.order.currency' => (string) $order->getOrderCurrencyCode(),
                'maho.payment.method' => (string) ($order->getPayment()?->getMethod() ?? ''),
            ], '{order}');
            $tracer->addCounter('maho.order.revenue', (float) $order->getGrandTotal(), [
                'maho.order.currency' => (string) $order->getOrderCurrencyCode(),
            ], '{currency_unit}');
        } catch (\Throwable) {
            // Telemetry must never affect order placement
        }
    }

    /**
     * Record an add-to-cart on the trace
     */
    #[\Maho\Config\Observer('checkout_cart_product_add_after')]
    public function addCartAddEvent(\Maho\Event\Observer $observer): void
    {
        $tracer = Mage::getTracer();
        if (!$tracer?->isEnabled()) {
            return;
        }
        try {
            $product = $observer->getEvent()->getProduct();
            if (!$product instanceof Mage_Catalog_Model_Product) {
                return;
            }
            $tracer->getActiveSpan()?->addEvent('maho.cart.add', [
                'maho.product.id' => (int) $product->getId(),
                'maho.product.sku' => (string) $product->getSku(),
            ]);
            $tracer->addCounter('maho.cart.additions', 1, [], '{addition}');
        } catch (\Throwable) {
            // Telemetry must never affect the cart
        }
    }

    /**
     * Record the checkout success page (order confirmed to the customer)
     */
    #[\Maho\Config\Observer('checkout_onepage_controller_success_action')]
    public function addCheckoutSuccessEvent(\Maho\Event\Observer $observer): void
    {
        $span = Mage::getTracer()?->getActiveSpan();
        if (!$span) {
            return;
        }
        try {
            $orderIds = $observer->getEvent()->getOrderIds();
            $span->addEvent('maho.checkout.success', [
                'maho.order.ids' => implode(',', array_map(strval(...), (array) $orderIds)),
            ]);
        } catch (\Throwable) {
            // Telemetry must never affect checkout
        }
    }

    /**
     * Record a customer login and tag the whole trace with the pseudonymous
     * customer id (enduser.id semconv), mirroring the admin-user tagging
     */
    #[\Maho\Config\Observer('customer_login')]
    public function addCustomerLoginEvent(\Maho\Event\Observer $observer): void
    {
        $tracer = Mage::getTracer();
        $span = $tracer?->getActiveSpan();
        if (!$span) {
            return;
        }
        try {
            $customer = $observer->getEvent()->getCustomer();
            if (!$customer instanceof Mage_Customer_Model_Customer) {
                return;
            }
            $span->addEvent('maho.customer.login');
            $tracer->getRootSpan()?->setAttribute('enduser.id', (string) $customer->getId());
        } catch (\Throwable) {
            // Telemetry must never affect login
        }
    }
}
