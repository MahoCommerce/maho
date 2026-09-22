<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api\GraphQL;

use Mage\Sales\Api\CreditMemo;
use Mage\Sales\Api\Order;
use Mage\Sales\Api\OrderCurrency;
use Mage\Sales\Api\OrderProvider;
use Mage\Sales\Api\OrderService;
use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\AdminAcl;
use Maho\ApiPlatform\Trait\AdminQuoteTrait;

/**
 * Order Mutation Handler.
 *
 * Handles all order-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class OrderMutationHandler
{
    use AdminQuoteTrait;

    public function __construct(private OrderService $orderService, private OrderProvider $orderProvider) {}

    /**
     * Handle placeOrder mutation
     */
    public function handlePlaceOrder(array $variables, array $context): array
    {
        AdminAcl::checkResource(Order::class);
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        $quote = $this->loadAdminQuote((int) $cartId);

        $paymentMethod = $variables['paymentMethod'] ?? null;
        $shippingMethod = $variables['shippingMethod'] ?? null;

        if ($paymentMethod) {
            $quote->getPayment()->setMethod($paymentMethod);
        }
        if ($shippingMethod && !$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod($shippingMethod);
            $shippingAddress->setCollectShippingRates();
        }

        if ($paymentMethod || $shippingMethod) {
            $quote->collectTotals()->save();
        }

        // Reject a method the client made up: after rates are collected the
        // chosen code must resolve to a real rate, otherwise a caller could
        // claim e.g. free shipping that the store does not actually offer.
        if ($shippingMethod && !$quote->isVirtual()
            && !$quote->getShippingAddress()->getShippingRateByCode($shippingMethod)) {
            throw ValidationException::invalidValue('shippingMethod', 'is not available for this address');
        }

        $result = $this->orderService->placeAdminOrder(
            $quote,
            $variables['guestEmail'] ?? null,
            $variables['orderNote'] ?? null,
            $variables['cashTendered'] ?? null,
            $context['admin_user_id'] ?? null,
        );

        $order = $result['order'];
        $invoiceAndShipment = $this->createInvoiceAndShipment($order);

        return ['placeOrder' => [
            'order' => [
                'orderId' => (int) $order->getId(),
                'incrementId' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'currency' => OrderCurrency::of($order),
                'grandTotal' => (float) $order->getGrandTotal(),
            ],
            'invoice' => $invoiceAndShipment['invoice'],
            'shipment' => $invoiceAndShipment['shipment'],
            'changeAmount' => $result['changeAmount'] ?? null,
        ]];
    }

    /**
     * Handle lookupOrder query
     */
    public function handleLookupOrder(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $incrementId = $variables['incrementId'] ?? $variables['orderNumber'] ?? null;
        if (!$incrementId) {
            throw ValidationException::requiredField('incrementId');
        }

        $order = $this->orderService->getOrder(incrementId: $incrementId);
        if (!$order) {
            throw NotFoundException::order();
        }

        return ['lookupOrder' => $this->mapOrder($order)];
    }

    /**
     * Handle getCustomerOrders query
     */
    public function handleGetCustomerOrders(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $customerId = $variables['customerId'] ?? null;
        $limit = max(1, min((int) ($variables['limit'] ?? 10), 100));

        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }

        $result = $this->orderService->getCustomerOrders((int) $customerId, 1, $limit);

        $orders = [];
        foreach ($result['orders'] as $order) {
            $orders[] = $this->mapOrder($order);
        }

        return ['customerOrders' => $orders];
    }

    /**
     * Handle recentOrders query
     */
    public function handleRecentOrders(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $storeId = $variables['storeId'] ?? null;
        $limit = max(1, min((int) ($variables['limit'] ?? 10), 100));

        $orders = $this->orderService->getRecentOrders($limit, $storeId ? (int) $storeId : null);

        $result = [];
        foreach ($orders as $order) {
            $result[] = $this->mapOrderSummary($order);
        }

        return ['recentOrders' => $result];
    }

    /**
     * Handle searchOrders query
     */
    public function handleSearchOrders(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $search = $variables['search'] ?? null;
        $storeId = $variables['storeId'] ?? null;
        $limit = max(1, min((int) ($variables['limit'] ?? 10), 100));

        if (!$search) {
            return ['searchOrders' => []];
        }

        $orders = $this->orderService->searchOrders($search, $storeId ? (int) $storeId : null, $limit);

        $result = [];
        foreach ($orders as $order) {
            $result[] = $this->mapOrderSummary($order);
        }

        return ['searchOrders' => $result];
    }

    /**
     * Handle processReturn mutation
     */
    public function handleProcessReturn(array $variables, array $context): array
    {
        AdminAcl::checkResource(CreditMemo::class);
        $orderId = $variables['orderId'] ?? null;
        $items = $variables['items'] ?? [];
        $adjustmentPositive = (float) ($variables['adjustmentPositive'] ?? 0);
        $adjustmentNegative = (float) ($variables['adjustmentNegative'] ?? 0);
        $comment = $variables['comment'] ?? 'Return';

        if (!$orderId) {
            throw ValidationException::requiredField('orderId');
        }
        if (empty($items)) {
            throw ValidationException::requiredField('items');
        }
        if (!empty($variables['refundToStoreCredit'])) {
            throw ValidationException::invalidValue('refundToStoreCredit', 'store credit refunds are not supported');
        }

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw NotFoundException::order();
        }

        return $this->buildProcessReturn($order, $items, $comment, $adjustmentPositive, $adjustmentNegative);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildProcessReturn(
        \Mage_Sales_Model_Order $order,
        array $items,
        ?string $comment,
        float $adjustmentPositive,
        float $adjustmentNegative,
    ): array {
        $creditmemoData = [
            'qtys' => [],
            'shipping_amount' => 0,
            'adjustment_positive' => $adjustmentPositive,
            'adjustment_negative' => $adjustmentNegative,
        ];

        foreach ($items as $itemData) {
            $orderItemId = $itemData['orderItemId'] ?? null;
            $qty = $itemData['qty'] ?? 0;
            if ($orderItemId && $qty > 0) {
                $creditmemoData['qtys'][$orderItemId] = $qty;
            }
        }

        try {
            // Refunds run through OrderService so this path and the REST/GraphQL
            // CreditMemo resource apply the same money rules and the same lock.
            $creditmemo = $this->orderService->createCreditMemoForOrder(
                $order,
                $creditmemoData,
                $comment,
                offlineRefund: false,
            );
        } catch (\Mage_Core_Exception $e) {
            throw ValidationException::invalidValue('return', $e->getMessage(), $e);
        } catch (\RuntimeException $e) {
            throw ValidationException::invalidValue('orderId', 'a refund is already in progress for this order', $e);
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw ValidationException::invalidValue('return', 'failed to process the return', $e);
        }

        if (!$creditmemo) {
            throw ValidationException::invalidValue('orderId', 'cannot create credit memo for this order');
        }

        return ['processReturn' => [
            'success' => true,
            'creditmemo' => [
                'id' => (int) $creditmemo->getId(),
                'incrementId' => $creditmemo->getIncrementId(),
                'currency' => OrderCurrency::of($creditmemo),
                'grandTotal' => (float) $creditmemo->getGrandTotal(),
                'createdAt' => $creditmemo->getCreatedAt(),
            ],
            'order' => $this->mapOrder($order->load($order->getId())),
        ]];
    }

    /**
     * Create invoice and shipment for an order, logging any failures
     *
     * @return array{invoice: ?array, shipment: ?array}
     */
    private function createInvoiceAndShipment(\Mage_Sales_Model_Order $order): array
    {
        $invoice = null;
        $shipment = null;

        try {
            $invoice = $this->orderService->createInvoiceForOrder($order);
            $shipment = $this->orderService->createShipmentForOrder($order);
            $order->load($order->getId());
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

        return [
            'invoice' => $invoice ? ['invoiceId' => (int) $invoice->getId(), 'incrementId' => $invoice->getIncrementId()] : null,
            'shipment' => $shipment ? ['shipmentId' => (int) $shipment->getId(), 'incrementId' => $shipment->getIncrementId()] : null,
        ];
    }

    /**
     * Map order summary for list views
     */
    private function mapOrderSummary(\Mage_Sales_Model_Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'id' => (int) $item->getId(),
                'name' => $item->getName(),
                'qty' => (int) $item->getQtyOrdered(),
            ];
        }

        $customerName = $order->getCustomerFirstname() && $order->getCustomerLastname()
            ? $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()
            : ($order->getBillingAddress() ? $order->getBillingAddress()->getName() : 'Guest');

        return [
            'id' => (int) $order->getId(),
            'incrementId' => $order->getIncrementId(),
            'status' => $order->getStatus(),
            'customerName' => $customerName,
            'createdAt' => \Mage::helper('core')->formatDate($order->getCreatedAt(), 'medium', true),
            'currency' => OrderCurrency::of($order),
            'grandTotal' => (float) $order->getGrandTotal(),
            'items' => $items,
        ];
    }

    /**
     * Map order to full response array.
     * Uses the Provider DTO to ensure api_order_dto_build fires, then reshapes
     * for GraphQL-specific format (nested money objects, computed fields).
     */
    public function mapOrder(\Mage_Sales_Model_Order $order): array
    {
        $dto = $this->orderProvider->mapToDto($order);
        $data = $dto->toArray();

        // Money fields are plain numbers; $data['currency'] (from the Order DTO)
        // names the currency once for the whole response.
        $data['grandTotal'] = (float) $order->getGrandTotal();
        $data['subtotal'] = (float) $order->getSubtotal();
        $data['taxAmount'] = (float) $order->getTaxAmount();
        $data['shippingAmount'] = (float) $order->getShippingAmount();
        $data['discountAmount'] = abs((float) $order->getDiscountAmount());
        $data['totalRefunded'] = (float) ($order->getTotalRefunded() ?? 0);

        // Add GraphQL-specific computed fields
        $data['canRefund'] = $order->canCreditmemo();

        // Enrich items with returnable qty, align by item ID
        $orderItemsById = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $orderItemsById[(int) $item->getId()] = $item;
        }
        foreach ($data['items'] as &$itemData) {
            $itemId = (int) ($itemData['id'] ?? 0);
            if (isset($orderItemsById[$itemId])) {
                $qtyRefunded = (float) $orderItemsById[$itemId]->getQtyRefunded();
                $qtyOrdered = (float) $orderItemsById[$itemId]->getQtyOrdered();
                $itemData['qtyReturnable'] = max(0, $qtyOrdered - $qtyRefunded);
            }
        }
        unset($itemData);

        return $data;
    }
}
