<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api\GraphQL;

use Mage\Checkout\Api\CartService;
use Mage\Sales\Api\OrderProvider;
use Mage\Sales\Api\OrderService;
use Mage\Sales\Api\PaymentService;
use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;

/**
 * Order Mutation Handler
 *
 * Handles all order-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class OrderMutationHandler
{
    private OrderService $orderService;
    private OrderProvider $orderProvider;
    private CartService $cartService;
    private PaymentService $paymentService;
    private \Mage\Sales\Api\PosPaymentMapper $posPaymentMapper;

    /**
     * POS payment method mapping
     */
    private const POS_PAYMENT_MAP = [
        'Cash' => 'cashondelivery',
        'cash' => 'cashondelivery',
        'Card' => 'purchaseorder',
        'card' => 'purchaseorder',
        'EFTPOS' => 'purchaseorder',
        'eftpos' => 'purchaseorder',
        'Split' => 'maho_pos_split',
        'split' => 'maho_pos_split',
        'GiftCard' => 'free',
        'giftcard' => 'free',  // Gift card covers full amount
        'Mobile' => 'purchaseorder',
        'mobile' => 'purchaseorder',
        'Afterpay' => 'purchaseorder',
        'afterpay' => 'purchaseorder',
    ];

    public function __construct(OrderService $orderService, OrderProvider $orderProvider, ?CartService $cartService = null)
    {
        $this->orderService = $orderService;
        $this->orderProvider = $orderProvider;
        $this->cartService = $cartService ?? new CartService();
        $this->paymentService = new PaymentService();
        $this->posPaymentMapper = new \Mage\Sales\Api\PosPaymentMapper();
    }

    /**
     * Handle placeOrder mutation
     */
    public function handlePlaceOrder(array $variables, array $context): array
    {
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        $quote = $this->loadPosQuote((int) $cartId);

        $paymentMethod = $variables['paymentMethod'] ?? null;
        if ($paymentMethod) {
            $paymentMethod = self::POS_PAYMENT_MAP[$paymentMethod] ?? $paymentMethod;
        }

        $this->cartService->preparePosQuote(
            $quote,
            $variables['shippingMethod'] ?? null,
            $paymentMethod,
            $variables['guestEmail'] ?? null,
        );

        $quote->collectTotals();
        $quote->save();

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
                'grandTotal' => [
                    'value' => (float) $order->getGrandTotal(),
                    'formatted' => \Mage::helper('core')->currency($order->getGrandTotal(), true, false),
                ],
            ],
            'invoice' => $invoiceAndShipment['invoice'],
            'shipment' => $invoiceAndShipment['shipment'],
            'changeAmount' => $result['changeAmount'] ?? null,
        ]];
    }

    /**
     * Handle placeOrderWithSplitPayments mutation
     */
    public function handlePlaceOrderWithSplitPayments(array $variables, array $context): array
    {
        $cartId = $variables['cartId'] ?? null;
        $payments = $variables['payments'] ?? [];
        $registerId = $variables['registerId'] ?? 1;

        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (empty($payments)) {
            throw ValidationException::requiredField('payments');
        }

        $quote = $this->loadPosQuote((int) $cartId);

        $this->cartService->preparePosQuote(
            $quote,
            $variables['shippingMethod'] ?? null,
            'maho_pos_split',
        );

        $quote->collectTotals();
        $quote->save();

        // Validate payment amounts before placing the order to avoid
        // leaving an order in an inconsistent state if validation fails
        $grandTotal = (float) $quote->getGrandTotal();
        $totalPayment = 0.0;
        foreach ($payments as $paymentData) {
            $amount = (float) ($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Each payment amount must be greater than 0');
            }
            $totalPayment += $amount;
        }

        $tolerance = 0.01;
        if ($totalPayment < $grandTotal - $tolerance) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                sprintf('Total payment (%.2f) is less than order total (%.2f)', $totalPayment, $grandTotal),
            );
        }

        $result = $this->orderService->placeAdminOrder($quote, null, null, null, $context['admin_user_id'] ?? null);

        $order = $result['order'];

        $createdPayments = $this->paymentService->recordMultiplePayments(
            (int) $order->getId(),
            (int) $registerId,
            $payments,
        );

        $savedPayments = [];
        foreach ($createdPayments as $posPayment) {
            $savedPayments[] = [
                'paymentId' => (int) $posPayment->getId(),
                'methodCode' => $posPayment->getMethodCode(),
                'methodLabel' => PaymentService::getMethodLabel($posPayment->getMethodCode()),
                'amount' => [
                    'value' => (float) $posPayment->getAmount(),
                    'formatted' => \Mage::helper('core')->currency($posPayment->getAmount(), true, false),
                ],
                'status' => $posPayment->getStatus(),
            ];
        }

        $invoiceAndShipment = $this->createInvoiceAndShipment($order);

        return ['placeOrderWithSplitPayments' => [
            'order' => [
                'orderId' => (int) $order->getId(),
                'incrementId' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'grandTotal' => [
                    'value' => (float) $order->getGrandTotal(),
                    'formatted' => \Mage::helper('core')->currency($order->getGrandTotal(), true, false),
                ],
            ],
            'invoice' => $invoiceAndShipment['invoice'],
            'shipment' => $invoiceAndShipment['shipment'],
            'payments' => $savedPayments,
        ]];
    }

    /**
     * Handle orderPayments query
     */
    public function handleOrderPayments(array $variables): array
    {
        $orderId = $variables['orderId'] ?? null;
        if (!$orderId) {
            throw ValidationException::requiredField('orderId');
        }

        $collection = $this->paymentService->getOrderPayments((int) $orderId);

        $result = [];
        foreach ($collection as $payment) {
            $dto = $this->posPaymentMapper->mapToDto($payment);
            $result[] = [
                'paymentId' => $dto->id,
                'orderId' => $dto->orderId,
                'registerId' => $dto->registerId,
                'methodCode' => $dto->methodCode,
                'methodLabel' => $dto->methodLabel,
                'amount' => [
                    'value' => $dto->amount,
                    'formatted' => \Mage::helper('core')->currency($dto->amount, true, false),
                ],
                'cardType' => $dto->cardType,
                'cardLast4' => $dto->cardLast4,
                'authCode' => $dto->authCode,
                'transactionId' => $dto->transactionId,
                'status' => $dto->status,
                'createdAt' => $dto->createdAt,
            ];
        }

        return ['orderPayments' => $result];
    }

    /**
     * Handle lookupOrder query
     */
    public function handleLookupOrder(array $variables): array
    {
        $incrementId = $variables['incrementId'] ?? $variables['orderNumber'] ?? null;
        if (!$incrementId) {
            throw ValidationException::requiredField('incrementId');
        }

        $order = \Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            throw NotFoundException::order();
        }

        return ['lookupOrder' => $this->mapOrder($order)];
    }

    /**
     * Handle getCustomerOrders query
     */
    public function handleGetCustomerOrders(array $variables): array
    {
        $customerId = $variables['customerId'] ?? null;
        $limit = $variables['limit'] ?? 10;

        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }

        try {
            $orders = \Mage::getModel('sales/order')->getCollection()
                ->addFieldToFilter('customer_id', (int) $customerId)
                ->setOrder('created_at', 'DESC')
                ->setPageSize((int) $limit);

            $result = [];
            foreach ($orders as $order) {
                $result[] = $this->mapOrder($order);
            }

            return ['customerOrders' => $result];
        } catch (\Exception $e) {
            \Mage::log('handleGetCustomerOrders error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), \Mage::LOG_ERROR, 'pos_api.log');
            throw $e;
        }
    }

    /**
     * Handle recentOrders query
     */
    public function handleRecentOrders(array $variables): array
    {
        $storeId = $variables['storeId'] ?? null;
        $limit = $variables['limit'] ?? 10;

        try {
            $orders = \Mage::getModel('sales/order')->getCollection()
                ->setOrder('created_at', 'DESC')
                ->setPageSize((int) $limit);

            if ($storeId) {
                $orders->addFieldToFilter('store_id', (int) $storeId);
            }

            $result = [];
            foreach ($orders as $order) {
                $result[] = $this->mapOrderSummary($order);
            }

            return ['recentOrders' => $result];
        } catch (\Exception $e) {
            \Mage::log('handleRecentOrders error: ' . $e->getMessage(), \Mage::LOG_ERROR, 'pos_api.log');
            throw $e;
        }
    }

    /**
     * Handle searchOrders query
     */
    public function handleSearchOrders(array $variables): array
    {
        $search = $variables['search'] ?? null;
        $storeId = $variables['storeId'] ?? null;
        $limit = $variables['limit'] ?? 10;

        if (!$search) {
            return ['searchOrders' => []];
        }

        try {
            $orders = \Mage::getModel('sales/order')->getCollection()
                ->setOrder('created_at', 'DESC')
                ->setPageSize((int) $limit);

            if ($storeId) {
                $orders->addFieldToFilter('store_id', (int) $storeId);
            }

            // Search by increment ID or customer name/email (OR condition)
            $escapedSearch = addcslashes($search, '%_');
            $orders->addFieldToFilter(
                ['increment_id', 'customer_email', 'customer_firstname', 'customer_lastname'],
                [
                    ['like' => "%{$escapedSearch}%"],
                    ['like' => "%{$escapedSearch}%"],
                    ['like' => "%{$escapedSearch}%"],
                    ['like' => "%{$escapedSearch}%"],
                ],
            );

            $result = [];
            foreach ($orders as $order) {
                $result[] = $this->mapOrderSummary($order);
            }

            return ['searchOrders' => $result];
        } catch (\Exception $e) {
            \Mage::log('handleSearchOrders error: ' . $e->getMessage(), \Mage::LOG_ERROR, 'pos_api.log');
            throw $e;
        }
    }

    /**
     * Handle processReturn mutation
     */
    public function handleProcessReturn(array $variables, array $context): array
    {
        $orderId = $variables['orderId'] ?? null;
        $items = $variables['items'] ?? [];
        $refundToStoreCredit = $variables['refundToStoreCredit'] ?? false;
        $adjustmentPositive = (float) ($variables['adjustmentPositive'] ?? 0);
        $adjustmentNegative = (float) ($variables['adjustmentNegative'] ?? 0);
        $comment = $variables['comment'] ?? 'POS Return';

        if (!$orderId) {
            throw ValidationException::requiredField('orderId');
        }
        if (empty($items)) {
            throw ValidationException::requiredField('items');
        }

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw NotFoundException::order();
        }

        if (!$order->canCreditmemo()) {
            throw ValidationException::invalidValue('orderId', 'cannot create credit memo for this order');
        }

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

            // Set refund to store credit if requested
            if ($refundToStoreCredit && $order->getCustomerId()) {
                $creditmemo->setCustomerBalanceRefundFlag(true);
                $creditmemo->setPaymentRefundDisallowed(1.0);
            }

            $creditmemo->addComment($comment, false);

            // Register and save credit memo
            $creditmemo->register();

            $transactionSave = \Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
            $transactionSave->save();

            // If refunding to store credit, create store credit
            if ($refundToStoreCredit && $order->getCustomerId()) {
                $this->createStoreCredit(
                    (int) $order->getCustomerId(),
                    (float) $creditmemo->getGrandTotal(),
                    'Refund from Order #' . $order->getIncrementId(),
                );
            }

            return ['processReturn' => [
                'success' => true,
                'creditmemo' => [
                    'id' => (int) $creditmemo->getId(),
                    'incrementId' => $creditmemo->getIncrementId(),
                    'grandTotal' => [
                        'value' => (float) $creditmemo->getGrandTotal(),
                        'formatted' => \Mage::helper('core')->currency($creditmemo->getGrandTotal(), true, false),
                    ],
                    'createdAt' => $creditmemo->getCreatedAt(),
                ],
                'order' => $this->mapOrder($order->load($order->getId())),
            ]];
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw ValidationException::invalidValue('return', 'failed to process: ' . $e->getMessage());
        }
    }

    /**
     * Load a quote by ID without store filtering, for admin/POS context
     */
    private function loadPosQuote(int $cartId): \Mage_Sales_Model_Quote
    {
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);
        if (!$quote || !$quote->getId()) {
            throw NotFoundException::cart();
        }
        return $quote;
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
     * Create store credit for customer
     */
    private function createStoreCredit(int $customerId, float $amount, string $comment): void
    {
        // Check if enterprise customer balance module exists
        $balanceClass = \Mage::getConfig()->getModelClassName('enterprise_customerbalance/balance');
        if ($balanceClass && class_exists($balanceClass)) {
            $balance = new $balanceClass();
            $balance->setCustomerId($customerId)
                ->setWebsiteId(\Mage::app()->getWebsite()->getId())
                ->setAmountDelta($amount)
                ->setComment($comment)
                ->save();
        }
        // For community edition, you might use a custom store credit module
        // This is a placeholder that can be extended
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
            'grandTotal' => [
                'value' => (float) $order->getGrandTotal(),
                'formatted' => \Mage::helper('core')->currency($order->getGrandTotal(), true, false),
            ],
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

        // Reshape money fields into {value, formatted} for GraphQL
        $data['grandTotal'] = $this->formatMoney($order->getGrandTotal());
        $data['subtotal'] = $this->formatMoney($order->getSubtotal());
        $data['taxAmount'] = $this->formatMoney($order->getTaxAmount());
        $data['shippingAmount'] = $this->formatMoney($order->getShippingAmount());
        $data['discountAmount'] = $this->formatMoney(abs((float) $order->getDiscountAmount()));
        $data['totalRefunded'] = $this->formatMoney($order->getTotalRefunded() ?? 0);

        // Add GraphQL-specific computed fields
        $data['canRefund'] = $order->canCreditmemo();

        // Enrich items with returnable qty — align by item ID
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

    private function formatMoney(float|string|null $amount): array
    {
        $value = (float) ($amount ?? 0);
        return [
            'value' => $value,
            'formatted' => \Mage::helper('core')->currency($value, true, false),
        ];
    }
}
