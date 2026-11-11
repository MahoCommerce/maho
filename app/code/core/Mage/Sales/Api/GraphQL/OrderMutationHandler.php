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
use Maho\ApiPlatform\Http\RequestInput;
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
        $input = RequestInput::of($variables);
        $cartId = $input->int('cartId');
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        $quote = $this->loadAdminQuote($cartId);

        $cartService = \Mage::getService('checkout/cart');
        $shippingMethod = $input->string('shippingMethod') ?? '';
        if ($shippingMethod !== '' && !$quote->isVirtual()) {
            $cartService->selectShippingMethod($quote, $shippingMethod);
        }
        $paymentMethod = $input->string('paymentMethod') ?? '';
        if ($paymentMethod !== '') {
            // The ACL check above proves the staff. The endpoint runs in a store view, never in the admin scope
            $cartService->selectPaymentMethod($quote, ['method' => $paymentMethod], CartRequest::backOfficeActor(true));
        }

        $placeOrder = fn(): array => $this->orderService->place(
            $quote,
            $input->string('guestEmail'),
            $input->string('orderNote'),
            \Mage_Checkout_Model_Actor::Staff,
            sendConfirmation: $input->bool('sendConfirmation'),
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
        $incrementId = RequestInput::of($variables)->string('incrementId', 'orderNumber');
        if (!$incrementId) {
            throw ValidationException::requiredField('incrementId');
        }

        return ['lookupOrder' => $this->mapOrder($this->orderService->getByIncrementId($incrementId))];
    }

    /**
     * Handle getCustomerOrders query
     */
    public function handleGetCustomerOrders(array $variables): array
    {
        AdminAcl::checkResource(Order::class);
        $input = RequestInput::of($variables);
        $customerId = $input->int('customerId');
        $limit = self::readLimit($input);

        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }

        $result = new OrderList()->getListForCustomer($customerId, 1, $limit);

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
        $input = RequestInput::of($variables);
        $storeId = $input->int('storeId');

        $orders = new OrderList()->getRecent(self::readLimit($input), $storeId ?: null);

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
        $input = RequestInput::of($variables);
        $search = $input->string('search');
        $storeId = $input->int('storeId');
        $limit = self::readLimit($input);

        if (!$search) {
            return ['searchOrders' => []];
        }

        $orders = new OrderList()->search($search, $storeId ?: null, $limit);

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
        $input = RequestInput::of($variables);
        $orderId = $input->int('orderId');
        $items = $input->rows('items') ?? [];
        $comment = $input->string('comment') ?? 'Return';

        if (!$orderId) {
            throw ValidationException::requiredField('orderId');
        }
        if ($items === []) {
            throw ValidationException::requiredField('items');
        }

        return $this->buildCreditMemo($this->orderService->getById($orderId), $items, $comment, $input);
    }

    /**
     * Return the limit argument of a list query, from 1 to 100, 10 when the query leaves it out.
     */
    private static function readLimit(RequestInput $input): int
    {
        return max(1, min($input->int('limit') ?? 10, 100));
    }

    /**
     * @param list<RequestInput> $items
     * @return array<string, mixed>
     */
    private function buildCreditMemo(\Mage_Sales_Model_Order $order, array $items, ?string $comment, RequestInput $input): array
    {
        $creditmemoData = ['qtys' => []];
        foreach (['adjustmentPositive' => 'adjustment_positive', 'adjustmentNegative' => 'adjustment_negative', 'shippingAmount' => 'shipping_amount'] as $field => $key) {
            $amount = $input->float($field);
            if ($amount !== null) {
                $creditmemoData[$key] = $amount;
            }
        }
        foreach ($items as $item) {
            $orderItemId = $item->int('orderItemId');
            if ($orderItemId) {
                $creditmemoData['qtys'][$orderItemId] = $item->float('qty') ?? 0.0;
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
