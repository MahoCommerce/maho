<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Order;
use Maho\ApiPlatform\ApiResource\OrderItem;
use Maho\ApiPlatform\ApiResource\OrderPrices;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\ApiResource\PosPayment;
use Maho\ApiPlatform\ApiResource\PaymentSummary;
use Maho\ApiPlatform\Service\OrderService;
use Maho\ApiPlatform\Service\PaymentService;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Order State Provider - Fetches order data for API Platform
 *
 * @implements ProviderInterface<Order>
 */
final class OrderProvider implements ProviderInterface
{
    use AuthenticationTrait;

    private OrderService $orderService;
    private PaymentService $paymentService;

    public function __construct(Security $security)
    {
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
        $this->security = $security;
    }

    /**
     * Provide order data based on operation type
     *
     * @return Order|Order[]|PosPayment[]|PaymentSummary[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Order|array|null
    {
        $operationName = $operation->getName();

        // Handle REST /customers/me/orders endpoint
        if ($operationName === 'my_orders') {
            return $this->getMyOrders($context);
        }

        // Handle REST collection endpoint (GET /orders)
        if ($operation instanceof CollectionOperationInterface && !in_array($operationName, ['orderPayments', 'orderPaymentSummary', 'customerOrders', 'my_orders'])) {
            return $this->getCollection($context);
        }

        // Handle orderPayments query - get all POS payments for an order
        if ($operationName === 'orderPayments') {
            $orderId = $context['args']['orderId'] ?? null;
            if (!$orderId) {
                return [];
            }

            return $this->getOrderPayments((int) $orderId);
        }

        // Handle orderPaymentSummary query - get payment summary grouped by method
        if ($operationName === 'orderPaymentSummary') {
            $orderId = $context['args']['orderId'] ?? null;
            if (!$orderId) {
                return [];
            }

            return $this->getOrderPaymentSummary((int) $orderId);
        }

        // Handle guestOrder query - get order by increment ID and access token
        if ($operationName === 'guestOrder') {
            $incrementId = $context['args']['incrementId'] ?? null;
            $accessToken = $context['args']['accessToken'] ?? null;

            if (!$incrementId || !$accessToken) {
                return null;
            }

            $order = $this->orderService->getGuestOrder($incrementId, $accessToken);
            return $order ? $this->mapToDto($order, $accessToken) : null;
        }

        // Handle customerOrders collection query
        if ($operationName === 'customerOrders') {
            $customerId = $context['customer_id'] ?? null;
            if (!$customerId) {
                return [];
            }

            $page = $context['args']['page'] ?? 1;
            $pageSize = $context['args']['pageSize'] ?? 20;
            $status = $context['args']['status'] ?? null;

            $result = $this->orderService->getCustomerOrders((int) $customerId, $page, $pageSize, $status);

            $orders = [];
            foreach ($result['orders'] as $order) {
                $orders[] = $this->mapToDto($order);
            }

            return $orders;
        }

        // Handle single order query by ID
        $orderId = $context['args']['id'] ?? $uriVariables['id'] ?? null;

        if (!$orderId) {
            return null;
        }

        $order = $this->orderService->getOrder((int) $orderId);

        // Verify customer access for non-admin requests
        $customerId = $context['customer_id'] ?? null;
        if ($order && $customerId && $order->getCustomerId() != $customerId) {
            return null;
        }

        return $order ? $this->mapToDto($order) : null;
    }

    /**
     * Get current customer's orders (REST /customers/me/orders)
     *
     * @return array<Order>
     */
    private function getMyOrders(array $context): array
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            return [];
        }

        $filters = $context['filters'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = min((int) ($filters['pageSize'] ?? 10), 50);
        $status = $filters['status'] ?? null;

        $result = $this->orderService->getCustomerOrders($customerId, $page, $pageSize, $status);

        $orders = [];
        foreach ($result['orders'] as $order) {
            $orders[] = $this->mapToDto($order);
        }

        return $orders;
    }

    /**
     * Get order collection with pagination
     *
     * @return array<Order>
     */
    private function getCollection(array $context): array
    {
        $filters = $context['filters'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = min((int) ($filters['pageSize'] ?? 20), 100);
        $status = $filters['status'] ?? null;

        $result = $this->orderService->getAllOrders($page, $pageSize, $status);

        $orders = [];
        foreach ($result['orders'] as $order) {
            $orders[] = $this->mapToDto($order);
        }

        return $orders;
    }

    /**
     * Map Maho order model to Order DTO
     */
    private function mapToDto(\Mage_Sales_Model_Order $order, ?string $accessToken = null): Order
    {
        $dto = new Order();
        $dto->id = (int) $order->getId();
        $dto->incrementId = $order->getIncrementId();
        $dto->customerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $dto->customerEmail = $order->getCustomerEmail();
        $dto->customerFirstname = $order->getCustomerFirstname();
        $dto->customerLastname = $order->getCustomerLastname();
        $dto->status = $order->getStatus();
        $dto->state = $order->getState();
        $dto->storeId = (int) $order->getStoreId();
        $dto->currency = $order->getOrderCurrencyCode() ?: 'AUD';
        $dto->totalItemCount = (int) $order->getTotalItemCount();
        $dto->totalQtyOrdered = (float) $order->getTotalQtyOrdered();
        $dto->createdAt = $order->getCreatedAt();
        $dto->updatedAt = $order->getUpdatedAt();
        $dto->couponCode = $order->getCouponCode();

        // Set access token for guest orders
        if ($accessToken) {
            $dto->accessToken = $accessToken;
        }

        // Map items
        $dto->items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $dto->items[] = $this->mapItemToDto($item);
        }

        // Map prices
        $dto->prices = $this->mapPricesToDto($order);

        // Map billing address
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $dto->billingAddress = $this->mapAddressToDto($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $dto->shippingAddress = $this->mapAddressToDto($shippingAddress);
        }

        // Map shipping method
        $dto->shippingMethod = $order->getShippingMethod();
        $dto->shippingDescription = $order->getShippingDescription();

        // Map payment method
        $payment = $order->getPayment();
        if ($payment) {
            $dto->paymentMethod = $payment->getMethod();
            try {
                $dto->paymentMethodTitle = $payment->getMethodInstance()->getTitle();
            } catch (\Exception $e) {
                $dto->paymentMethodTitle = $payment->getMethod();
            }

            // Get change amount for cash payments
            $changeAmount = $payment->getAdditionalInformation('change_amount');
            if ($changeAmount !== null) {
                $dto->changeAmount = (float) $changeAmount;
            }
        }

        // Map status history
        $dto->statusHistory = $this->orderService->getOrderNotes($order);

        return $dto;
    }

    /**
     * Map Maho order item model to OrderItem DTO
     */
    private function mapItemToDto(\Mage_Sales_Model_Order_Item $item): OrderItem
    {
        $dto = new OrderItem();
        $dto->id = (int) $item->getId();
        $dto->sku = $item->getSku();
        $dto->name = $item->getName() ?? '';
        $dto->qty = (float) $item->getQtyOrdered();
        $dto->qtyOrdered = (float) $item->getQtyOrdered();
        $dto->qtyShipped = (float) $item->getQtyShipped();
        $dto->qtyRefunded = (float) $item->getQtyRefunded();
        $dto->qtyCanceled = (float) $item->getQtyCanceled();
        $dto->price = (float) $item->getPrice();
        $dto->priceInclTax = (float) $item->getPriceInclTax();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
        $dto->discountAmount = $item->getDiscountAmount() ? (float) $item->getDiscountAmount() : null;
        $dto->discountPercent = $item->getDiscountPercent() ? (float) $item->getDiscountPercent() : null;
        $dto->taxAmount = $item->getTaxAmount() ? (float) $item->getTaxAmount() : null;
        $dto->taxPercent = $item->getTaxPercent() ? (float) $item->getTaxPercent() : null;
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->parentItemId = $item->getParentItemId() ? (int) $item->getParentItemId() : null;

        return $dto;
    }

    /**
     * Map Maho order to OrderPrices DTO
     */
    private function mapPricesToDto(\Mage_Sales_Model_Order $order): OrderPrices
    {
        $prices = new OrderPrices();

        $prices->subtotal = (float) $order->getSubtotal();
        $prices->subtotalInclTax = (float) $order->getSubtotalInclTax();
        $prices->discountAmount = $order->getDiscountAmount()
            ? abs((float) $order->getDiscountAmount())
            : null;
        $prices->shippingAmount = $order->getShippingAmount()
            ? (float) $order->getShippingAmount()
            : null;
        $prices->shippingAmountInclTax = $order->getShippingInclTax()
            ? (float) $order->getShippingInclTax()
            : null;
        $prices->taxAmount = (float) $order->getTaxAmount();
        $prices->grandTotal = (float) $order->getGrandTotal();
        $prices->totalPaid = (float) $order->getTotalPaid();
        $prices->totalRefunded = (float) $order->getTotalRefunded();
        $prices->totalDue = (float) $order->getTotalDue();

        // Check for giftcard amount if available
        $giftcardAmount = $order->getData('giftcard_amount');
        if ($giftcardAmount) {
            $prices->giftcardAmount = abs((float) $giftcardAmount);
        }

        return $prices;
    }

    /**
     * Map Maho order address model to Address DTO
     */
    private function mapAddressToDto(\Mage_Sales_Model_Order_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
    }

    /**
     * Get all POS payments for an order
     *
     * @return PosPayment[]
     */
    private function getOrderPayments(int $orderId): array
    {
        $collection = $this->paymentService->getOrderPayments($orderId);
        $payments = [];

        $methodLabels = [
            'cashondelivery' => 'Cash',
            'cash' => 'Cash',
            'purchaseorder' => 'EFTPOS/Card',
            'eftpos' => 'EFTPOS/Card',
            'gene_braintree_creditcard' => 'Credit Card',
            'checkmo' => 'Check/Money Order',
            'banktransfer' => 'Bank Transfer',
        ];

        foreach ($collection as $payment) {
            $dto = new PosPayment();
            $dto->id = (int) $payment->getId();
            $dto->orderId = (int) $payment->getOrderId();
            $dto->registerId = (int) $payment->getRegisterId();
            $dto->methodCode = $payment->getMethodCode();
            $dto->methodLabel = $methodLabels[$payment->getMethodCode()] ?? $payment->getMethodCode();
            $dto->amount = (float) $payment->getAmount();
            $dto->baseAmount = (float) $payment->getBaseAmount();
            $dto->currencyCode = $payment->getCurrencyCode();
            $dto->terminalId = $payment->getTerminalId();
            $dto->transactionId = $payment->getTransactionId();
            $dto->cardType = $payment->getCardType();
            $dto->cardLast4 = $payment->getCardLast4();
            $dto->authCode = $payment->getAuthCode();
            $dto->status = $payment->getStatus();
            $dto->createdAt = $payment->getCreatedAt();

            $payments[] = $dto;
        }

        return $payments;
    }

    /**
     * Get payment summary grouped by method
     *
     * @return PaymentSummary[]
     */
    private function getOrderPaymentSummary(int $orderId): array
    {
        $collection = $this->paymentService->getOrderPayments($orderId);

        $methodLabels = [
            'cashondelivery' => 'Cash',
            'cash' => 'Cash',
            'purchaseorder' => 'EFTPOS/Card',
            'eftpos' => 'EFTPOS/Card',
            'gene_braintree_creditcard' => 'Credit Card',
            'checkmo' => 'Check/Money Order',
            'banktransfer' => 'Bank Transfer',
        ];

        // Group payments by method
        $grouped = [];
        foreach ($collection as $payment) {
            $method = $payment->getMethodCode();
            if (!isset($grouped[$method])) {
                $grouped[$method] = [
                    'method' => $method,
                    'methodTitle' => $methodLabels[$method] ?? $method,
                    'totalAmount' => 0.0,
                    'paymentCount' => 0,
                ];
            }
            $grouped[$method]['totalAmount'] += (float) $payment->getAmount();
            $grouped[$method]['paymentCount']++;
        }

        // Convert to DTOs
        $summaries = [];
        foreach ($grouped as $data) {
            $dto = new PaymentSummary();
            $dto->method = $data['method'];
            $dto->methodTitle = $data['methodTitle'];
            $dto->totalAmount = round($data['totalAmount'], 2);
            $dto->paymentCount = $data['paymentCount'];
            $summaries[] = $dto;
        }

        return $summaries;
    }
}
