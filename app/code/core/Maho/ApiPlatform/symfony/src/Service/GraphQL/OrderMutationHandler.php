<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service\GraphQL;

use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Service\OrderService;

/**
 * Order Mutation Handler
 *
 * Handles all order-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class OrderMutationHandler
{
    private OrderService $orderService;

    /**
     * Fallback payment method labels (used when model title unavailable)
     */
    private const PAYMENT_METHOD_LABELS = [
        'cashondelivery' => 'Cash',
        'cash' => 'Cash',
        'purchaseorder' => 'EFTPOS/Card',
        'gene_braintree_creditcard' => 'Credit Card',
        'checkmo' => 'Check/Money Order',
        'banktransfer' => 'Bank Transfer',
    ];

    /**
     * Get payment method display label, preferring store-configured title
     */
    private function getPaymentMethodLabel(string $code): string
    {
        try {
            $method = \Mage::helper('payment')->getMethodInstance($code);
            if ($method) {
                $title = $method->getTitle();
                if ($title) {
                    return $title;
                }
            }
        } catch (\Exception $e) {
            // Method not available, fall through to hardcoded labels
        }
        return self::PAYMENT_METHOD_LABELS[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

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

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
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

        // Load quote without store filtering for admin/POS context
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);
        if (!$quote || !$quote->getId()) {
            throw NotFoundException::cart();
        }

        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        // POS default address
        $posAddress = \Maho\ApiPlatform\Service\StoreDefaults::getPosAddress($quote->getStoreId() ? (int) $quote->getStoreId() : null);


        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getFirstname()) {
                $shippingAddress->addData($posAddress);
            }
            $shippingMethod = $variables['shippingMethod'] ?? null;
            if (!$shippingAddress->getShippingMethod() || $shippingMethod) {
                $method = $shippingMethod ?: 'freeshipping_freeshipping';
                $shippingAddress->setShippingMethod($method);
                if ($method === 'freeshipping_freeshipping') {
                    $shippingAddress->setShippingDescription('Free Shipping - POS Pickup');
                    $shippingAddress->setShippingAmount(0);
                    $shippingAddress->setBaseShippingAmount(0);
                }
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            $billingAddress->addData($posAddress);
        }

        $payment = $quote->getPayment();
        $paymentMethod = $variables['paymentMethod'] ?? null;
        if (!$payment->getMethod() || $paymentMethod) {
            $method = $paymentMethod ?: 'cashondelivery';
            $method = self::POS_PAYMENT_MAP[$method] ?? $method;
            $payment->setMethod($method);
        }

        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail($variables['guestEmail'] ?? 'pos@store.local');
        }

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
        $invoice = null;
        $shipment = null;

        try {
            if ($order->canInvoice()) {
                $invoice = \Mage::getModel('sales/service_order', $order)
                    ->prepareInvoice()
                    ->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)
                    ->register();
                $invoice->getOrder()->setIsInProcess(true);
                \Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }
            if ($order->canShip()) {
                $shipment = \Mage::getModel('sales/service_order', $order)->prepareShipment();
                $shipment->register();
                \Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            }
            $order->load($order->getId());
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

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
            'invoice' => $invoice ? ['invoiceId' => (int) $invoice->getId(), 'incrementId' => $invoice->getIncrementId()] : null,
            'shipment' => $shipment ? ['shipmentId' => (int) $shipment->getId(), 'incrementId' => $shipment->getIncrementId()] : null,
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

        // Load quote without store filtering for admin/POS context
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);
        if (!$quote || !$quote->getId()) {
            throw NotFoundException::cart();
        }

        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        $posAddress = \Maho\ApiPlatform\Service\StoreDefaults::getPosAddress($quote->getStoreId() ? (int) $quote->getStoreId() : null);


        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getFirstname()) {
                $shippingAddress->addData($posAddress);
            }
            $shippingMethod = $variables['shippingMethod'] ?? null;
            if (!$shippingAddress->getShippingMethod() || $shippingMethod) {
                $method = $shippingMethod ?: 'freeshipping_freeshipping';
                $shippingAddress->setShippingMethod($method);
                if ($method === 'freeshipping_freeshipping') {
                    $shippingAddress->setShippingDescription('Free Shipping - POS Pickup');
                    $shippingAddress->setShippingAmount(0);
                    $shippingAddress->setBaseShippingAmount(0);
                }
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            $billingAddress->addData($posAddress);
        }

        $payment = $quote->getPayment();
        $payment->setMethod('maho_pos_split');

        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail('pos@store.local');
        }

        $quote->collectTotals();
        $quote->save();

        $result = $this->orderService->placeAdminOrder($quote, null, null, null, $context['admin_user_id'] ?? null);

        $order = $result['order'];
        $grandTotal = (float) $order->getGrandTotal();
        $invoice = null;
        $shipment = null;
        $savedPayments = [];

        // Validate individual payment amounts and total
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

        foreach ($payments as $paymentData) {
            /** @var \Maho_Pos_Model_Payment $posPayment */
            $posPayment = \Mage::getModel('maho_pos/payment');
            $posPayment->setOrderId((int) $order->getId())
                ->setRegisterId((int) $registerId)
                ->setMethodCode($paymentData['methodCode'])
                ->setAmount((float) $paymentData['amount'])
                ->setBaseAmount((float) $paymentData['amount'])
                ->setCurrencyCode($order->getOrderCurrencyCode())
                ->setStatus('captured');

            if (!empty($paymentData['cardType'])) {
                $posPayment->setCardType($paymentData['cardType']);
            }
            if (!empty($paymentData['cardLast4'])) {
                $posPayment->setCardLast4($paymentData['cardLast4']);
            }
            if (!empty($paymentData['authCode'])) {
                $posPayment->setAuthCode($paymentData['authCode']);
            }
            if (!empty($paymentData['transactionId'])) {
                $posPayment->setTransactionId($paymentData['transactionId']);
            }
            if (!empty($paymentData['terminalId'])) {
                $posPayment->setTerminalId($paymentData['terminalId']);
            }

            $posPayment->save();

            $savedPayments[] = [
                'paymentId' => (int) $posPayment->getId(),
                'methodCode' => $posPayment->getMethodCode(),
                'methodLabel' => $this->getPaymentMethodLabel($posPayment->getMethodCode()),
                'amount' => [
                    'value' => (float) $posPayment->getAmount(),
                    'formatted' => \Mage::helper('core')->currency($posPayment->getAmount(), true, false),
                ],
                'status' => $posPayment->getStatus(),
            ];
        }

        try {
            if ($order->canInvoice()) {
                $invoice = \Mage::getModel('sales/service_order', $order)
                    ->prepareInvoice()
                    ->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)
                    ->register();
                $invoice->getOrder()->setIsInProcess(true);
                \Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }
            if ($order->canShip()) {
                $shipment = \Mage::getModel('sales/service_order', $order)->prepareShipment();
                $shipment->register();
                \Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            }
            $order->load($order->getId());
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

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
            'invoice' => $invoice ? ['invoiceId' => (int) $invoice->getId(), 'incrementId' => $invoice->getIncrementId()] : null,
            'shipment' => $shipment ? ['shipmentId' => (int) $shipment->getId(), 'incrementId' => $shipment->getIncrementId()] : null,
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

        /** @var \Maho_Pos_Model_Payment $paymentModel */
        $paymentModel = \Mage::getModel('maho_pos/payment');
        /** @var \Maho_Pos_Model_Resource_Payment_Collection $payments */
        $payments = $paymentModel->getCollection()
            ->addFieldToFilter('order_id', (int) $orderId)
            ->setOrder('created_at', 'ASC');

        $result = [];
        foreach ($payments as $payment) {
            $result[] = [
                'paymentId' => (int) $payment->getId(),
                'orderId' => (int) $payment->getOrderId(),
                'registerId' => (int) $payment->getRegisterId(),
                'methodCode' => $payment->getMethodCode(),
                'methodLabel' => $this->getPaymentMethodLabel($payment->getMethodCode()),
                'amount' => [
                    'value' => (float) $payment->getAmount(),
                    'formatted' => \Mage::helper('core')->currency($payment->getAmount(), true, false),
                ],
                'cardType' => $payment->getCardType(),
                'cardLast4' => $payment->getCardLast4(),
                'authCode' => $payment->getAuthCode(),
                'transactionId' => $payment->getTransactionId(),
                'status' => $payment->getStatus(),
                'createdAt' => $payment->getCreatedAt(),
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
     * Map order to full response array
     */
    public function mapOrder(\Mage_Sales_Model_Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $qtyRefunded = (float) $item->getQtyRefunded();
            $qtyOrdered = (float) $item->getQtyOrdered();
            $qtyReturnable = $qtyOrdered - $qtyRefunded;

            $items[] = [
                'id' => (int) $item->getId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'price' => (float) $item->getPrice(),
                'priceInclTax' => (float) $item->getPriceInclTax(),
                'qtyOrdered' => $qtyOrdered,
                'qtyRefunded' => $qtyRefunded,
                'qtyReturnable' => max(0, $qtyReturnable),
                'rowTotal' => (float) $item->getRowTotal(),
                'rowTotalInclTax' => (float) $item->getRowTotalInclTax(),
                'discountAmount' => (float) $item->getDiscountAmount(),
                'taxAmount' => (float) $item->getTaxAmount(),
            ];
        }

        // Get customer info
        $customerName = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
        if (!trim($customerName)) {
            $customerName = $order->getBillingAddress() ? $order->getBillingAddress()->getName() : 'Guest';
        }

        return [
            'id' => (int) $order->getId(),
            'incrementId' => $order->getIncrementId(),
            'status' => $order->getStatus(),
            'state' => $order->getState(),
            'customerId' => $order->getCustomerId() ? (int) $order->getCustomerId() : null,
            'customerName' => trim($customerName),
            'customerEmail' => $order->getCustomerEmail(),
            'grandTotal' => [
                'value' => (float) $order->getGrandTotal(),
                'formatted' => \Mage::helper('core')->currency($order->getGrandTotal(), true, false),
            ],
            'subtotal' => [
                'value' => (float) $order->getSubtotal(),
                'formatted' => \Mage::helper('core')->currency($order->getSubtotal(), true, false),
            ],
            'taxAmount' => [
                'value' => (float) $order->getTaxAmount(),
                'formatted' => \Mage::helper('core')->currency($order->getTaxAmount(), true, false),
            ],
            'shippingAmount' => [
                'value' => (float) $order->getShippingAmount(),
                'formatted' => \Mage::helper('core')->currency($order->getShippingAmount(), true, false),
            ],
            'discountAmount' => [
                'value' => abs((float) $order->getDiscountAmount()),
                'formatted' => \Mage::helper('core')->currency(abs((float) $order->getDiscountAmount()), true, false),
            ],
            'totalRefunded' => [
                'value' => (float) ($order->getTotalRefunded() ?? 0),
                'formatted' => \Mage::helper('core')->currency($order->getTotalRefunded() ?? 0, true, false),
            ],
            'items' => $items,
            'canRefund' => $order->canCreditmemo(),
            'paymentMethod' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
            'shippingMethod' => $order->getShippingMethod(),
            'shippingDescription' => $order->getShippingDescription(),
            'createdAt' => $order->getCreatedAt(),
            'updatedAt' => $order->getUpdatedAt(),
        ];
    }
}
