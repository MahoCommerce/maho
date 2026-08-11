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

    private OrderService $orderService;
    private OrderProvider $orderProvider;

    public function __construct(OrderService $orderService, OrderProvider $orderProvider)
    {
        $this->orderService = $orderService;
        $this->orderProvider = $orderProvider;
    }

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
            $shippingAddress->setCollectShippingRates(1);
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

        // Serialize concurrent refunds on the same order so two requests can't
        // both pass canCreditmemo() and both register(), double-refunding.
        // Mirrors OrderService::placeAdminOrder() and CreditMemoProcessor.
        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        // Shared per-order lock so refunds are mutually exclusive with the
        // order's other state transitions (invoice/ship/cancel). Matches
        // OrderService::withOrderLock() and CreditMemoProcessor.
        $lockName = 'maho_order_mutate:' . (int) $order->getId();
        if (!$write->getLock($lockName, 5)) {
            throw ValidationException::invalidValue('orderId', 'a refund is already in progress for this order');
        }

        try {
            // Re-read the order under the lock so canCreditmemo() sees the live
            // total_refunded, not a value another request changed while waiting.
            $order->load($orderId);
            if (!$order->canCreditmemo()) {
                throw ValidationException::invalidValue('orderId', 'cannot create credit memo for this order');
            }

            return $this->buildProcessReturn($order, $items, $comment, $adjustmentPositive, $adjustmentNegative);
        } finally {
            $write->releaseLock($lockName);
        }
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
        // Build credit memo data
        $creditmemoData = [
            'qtys' => [],
            'shipping_amount' => 0,
            'adjustment_positive' => $adjustmentPositive,
            'adjustment_negative' => $adjustmentNegative,
            'comment_text' => $comment,
            'send_email' => false,
        ];

        // Map items to credit memo quantities
        foreach ($items as $itemData) {
            $orderItemId = $itemData['orderItemId'] ?? null;
            $qty = $itemData['qty'] ?? 0;
            if ($orderItemId && $qty > 0) {
                $creditmemoData['qtys'][$orderItemId] = $qty;
            }
        }

        try {
            /** @var \Mage_Sales_Model_Service_Order $service */
            $service = \Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareCreditmemo($creditmemoData);

            if ((float) $creditmemo->getGrandTotal() <= 0) {
                throw ValidationException::invalidValue('grandTotal', 'credit memo grand total must be positive');
            }

            // Cap at the order's refundable balance so an inflated adjustment
            // can't over-refund or mint excess store credit.
            $refundable = (float) $order->getBaseTotalPaid() - (float) $order->getBaseTotalRefunded();
            if ((float) $creditmemo->getBaseGrandTotal() > $refundable + 0.0001) {
                throw ValidationException::invalidValue('adjustmentPositive', 'refund amount exceeds the order\'s refundable balance');
            }

            if ($comment !== null && $comment !== '') {
                $creditmemo->addComment($comment, false);
            }

            // Register and save credit memo
            $creditmemo->register();

            $transactionSave = \Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
            $transactionSave->save();

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
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw ValidationException::invalidValue('return', 'failed to process the return', $e);
        }
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
