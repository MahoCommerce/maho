<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api\GraphQL;

use Mage\Checkout\Api\CartRequest;
use Mage\Sales\Api\CreditMemo;
use Mage\Sales\Api\Order;
use Mage\Sales\Api\OrderCurrency;
use Mage\Sales\Api\OrderList;
use Mage\Sales\Api\OrderProvider;
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

    private readonly \Mage_Sales_Service_Order $orderService;

    public function __construct(private OrderProvider $orderProvider)
    {
        $this->orderService = \Mage::getService('sales/order');
    }

    /**
     * Handle placeOrder mutation
     */
    public function handlePlaceOrder(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        $quote = $this->loadAdminQuote((int) $cartId);

        $cartService = \Mage::getService('checkout/cart');
        $shippingMethod = (string) ($variables['shippingMethod'] ?? '');
        if ($shippingMethod !== '' && !$quote->isVirtual()) {
            $cartService->selectShippingMethod($quote, $shippingMethod);
        }
        $paymentMethod = (string) ($variables['paymentMethod'] ?? '');
        if ($paymentMethod !== '') {
            // The ACL check above proves the staff. The endpoint runs in a store view, never in the admin scope
            $cartService->selectPaymentMethod($quote, ['method' => $paymentMethod], CartRequest::backOfficeActor(true));
        }

        $placeOrder = fn(): array => $this->orderService->place(
            $quote,
            $variables['guestEmail'] ?? null,
            $variables['orderNote'] ?? null,
            \Mage_Checkout_Model_Actor::Staff,
            sendConfirmation: isset($variables['sendConfirmation']) ? (bool) $variables['sendConfirmation'] : null,
        );
        // A caller in the admin scope places a backend order (issue #1337). Any other caller places in the store of the cart.
        $result = \Mage::app()->getStore()->isAdmin() ? $placeOrder() : $cartService->inQuoteStoreScope($quote, $placeOrder);

        $order = $result['order'];

        return ['placeOrder' => [
            'order' => [
                'orderId' => (int) $order->getId(),
                'incrementId' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'currency' => OrderCurrency::of($order),
                'grandTotal' => (float) $order->getGrandTotal(),
            ],
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

        return ['lookupOrder' => $this->mapOrder($this->orderService->getByIncrementId((string) $incrementId))];
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

        $result = new OrderList()->getListForCustomer((int) $customerId, 1, $limit);

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

        $orders = new OrderList()->getRecent($limit, $storeId ? (int) $storeId : null);

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

        $orders = new OrderList()->search($search, $storeId ? (int) $storeId : null, $limit);

        $result = [];
        foreach ($orders as $order) {
            $result[] = $this->mapOrderSummary($order);
        }

        return ['searchOrders' => $result];
    }

    /**
     * Handle createCreditMemo mutation: refund the listed items of an order offline
     */
    public function handleCreateCreditMemo(array $variables, array $context): array
    {
        AdminAcl::checkResource(CreditMemo::class);
        $orderId = $variables['orderId'] ?? null;
        $items = $variables['items'] ?? [];
        $comment = $variables['comment'] ?? 'Return';

        if (!$orderId) {
            throw ValidationException::requiredField('orderId');
        }
        if (empty($items)) {
            throw ValidationException::requiredField('items');
        }

        return $this->buildCreditMemo($this->orderService->getById((int) $orderId), $items, $comment, $variables);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function buildCreditMemo(\Mage_Sales_Model_Order $order, array $items, ?string $comment, array $variables): array
    {
        $creditmemoData = ['qtys' => []];
        foreach (['adjustmentPositive' => 'adjustment_positive', 'adjustmentNegative' => 'adjustment_negative', 'shippingAmount' => 'shipping_amount'] as $field => $key) {
            if (isset($variables[$field])) {
                $creditmemoData[$key] = (float) $variables[$field];
            }
        }
        foreach ($items as $itemData) {
            $orderItemId = $itemData['orderItemId'] ?? null;
            if ($orderItemId) {
                $creditmemoData['qtys'][$orderItemId] = $itemData['qty'] ?? 0;
            }
        }

        $creditmemo = \Mage::getService('sales/order_document')->refund(
            $order,
            $creditmemoData,
            $comment,
            offlineRefund: true,
        );

        return ['createCreditMemo' => [
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
