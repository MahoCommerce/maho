<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Mage\Customer\Api\Address;
use Mage\Customer\Api\AddressMapper;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Order State Provider - Fetches order data for API Platform
 */
final class OrderProvider extends \Maho\ApiPlatform\Provider
{
    private AddressMapper $addressMapper;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private readonly PosPaymentMapper $posPaymentMapper;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->addressMapper = new AddressMapper();
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
        $this->posPaymentMapper = new PosPaymentMapper();
    }

    /**
     * Provide order data based on operation type
     *
     * @return Order|TraversablePaginator<Order>|PosPayment[]|PaymentSummary[]|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Order|TraversablePaginator|array|null
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
                return new TraversablePaginator(new \ArrayIterator([]), 1, 20, 0);
            }

            $page = $context['args']['page'] ?? 1;
            $pageSize = max(1, min((int) ($context['args']['pageSize'] ?? 20), 100));
            $status = $context['args']['status'] ?? null;

            $result = $this->orderService->getCustomerOrders((int) $customerId, $page, $pageSize, $status);

            $orders = [];
            foreach ($result['orders'] as $order) {
                $orders[] = $this->mapToDto($order);
            }

            return new TraversablePaginator(new \ArrayIterator($orders), $page, $pageSize, (int) ($result['total'] ?? count($orders)));
        }

        // Handle single order query by ID
        $orderId = $context['args']['id'] ?? $uriVariables['id'] ?? null;

        if (!$orderId) {
            return null;
        }

        $order = $this->orderService->getOrder((int) $orderId);

        if (!$order) {
            return null;
        }

        // Verify access to this order
        // - Admins can access any order
        // - API users with orders/read permission can access any order (for integrations)
        // - Customers can only access their own orders
        if (!$this->canAccessOrder($order)) {
            return null;
        }

        return $this->mapToDto($order);
    }

    /**
     * Check if current user can access the given order
     *
     * - Admins: full access
     * - API users (ROLE_API_USER): full access (permission already checked by security.yaml)
     * - Customers: own orders only
     */
    private function canAccessOrder(\Mage_Sales_Model_Order $order): bool
    {
        // Admins can access any order
        if ($this->isAdmin()) {
            return true;
        }

        // API users with orders/read permission can access any order
        // (permission enforcement is handled by security.yaml + ApiUserVoter)
        $user = $this->security->getUser();
        if ($user instanceof \Maho\ApiPlatform\Security\ApiUser && $user->isApiUser()) {
            return true;
        }

        // Customers can only access their own orders
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();
        if ($authenticatedCustomerId !== null) {
            $orderCustomerId = $order->getCustomerId();
            return $orderCustomerId && (int) $orderCustomerId === $authenticatedCustomerId;
        }

        // No valid authentication context
        return false;
    }

    /**
     * Get current customer's orders (REST /customers/me/orders)
     *
     * @return TraversablePaginator<Order>
     */
    private function getMyOrders(array $context): TraversablePaginator
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            return new TraversablePaginator(new \ArrayIterator([]), 1, 10, 0);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
        $filters = $context['filters'] ?? [];
        $status = $filters['status'] ?? null;

        $result = $this->orderService->getCustomerOrders($customerId, $page, $pageSize, $status);

        $orders = [];
        foreach ($result['orders'] as $order) {
            $orders[] = $this->mapToDto($order);
        }

        return new TraversablePaginator(new \ArrayIterator($orders), $page, $pageSize, (int) ($result['total'] ?? count($orders)));
    }

    /**
     * Get order collection with pagination
     *
     * @return TraversablePaginator<Order>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        $this->requireAdminOrApiUser('Order listing requires admin or API access');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context);
        $filters = $context['filters'] ?? [];
        $status = $filters['status'] ?? null;
        $email = $filters['email'] ?? null;
        $emailLike = $filters['emailLike'] ?? null;
        $incrementId = $filters['incrementId'] ?? null;
        $since = $filters['since'] ?? null;

        $result = $this->orderService->getAllOrders($page, $pageSize, $status, $email, $incrementId, $emailLike, $since);

        $orders = [];
        foreach ($result['orders'] as $order) {
            $orders[] = $this->mapToDto($order);
        }

        return new TraversablePaginator(new \ArrayIterator($orders), $page, $pageSize, (int) ($result['total'] ?? count($orders)));
    }

    /**
     * Map Maho order model to Order DTO
     */
    public function mapToDto(\Mage_Sales_Model_Order $order, ?string $accessToken = null): Order
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
        $dto->currency = $order->getOrderCurrencyCode() ?: \Mage::app()->getStore()->getDefaultCurrencyCode();
        $dto->totalItemCount = (int) $order->getTotalItemCount();
        $dto->totalQtyOrdered = (float) $order->getTotalQtyOrdered();
        $dto->createdAt = $order->getCreatedAt();
        $dto->updatedAt = $order->getUpdatedAt();
        $dto->couponCode = $order->getCouponCode();

        // Set access token for guest orders
        if ($accessToken) {
            $dto->accessToken = $accessToken;
        }

        // Map items — use preloaded items if available (batch-loaded), otherwise load.
        $dto->items = [];
        $preloadedItems = $order->getData('_preloaded_items');
        if ($preloadedItems) {
            foreach ($preloadedItems as $item) {
                $dto->items[] = $this->mapItemToDto($item);
            }
        } else {
            foreach ($order->getAllVisibleItems() as $item) {
                $dto->items[] = $this->mapItemToDto($item);
            }
        }

        // Map prices
        $dto->prices = $this->mapPricesToArray($order);

        // Map billing address — use joined data if available, otherwise load.
        if ($order->getData('billing_telephone') !== null) {
            $dto->billingAddress = new Address();
            $dto->billingAddress->id = (int) ($order->getData('billing_addr_id') ?: 0);
            $dto->billingAddress->firstName = $order->getData('billing_firstname') ?? '';
            $dto->billingAddress->lastName = $order->getData('billing_lastname') ?? '';
            $dto->billingAddress->company = $order->getData('billing_company');
            $street = $order->getData('billing_street') ?? '';
            $dto->billingAddress->street = $street ? explode("\n", $street) : [];
            $dto->billingAddress->city = $order->getData('billing_city') ?? '';
            $dto->billingAddress->region = $order->getData('billing_region');
            $dto->billingAddress->postcode = $order->getData('billing_postcode') ?? '';
            $dto->billingAddress->countryId = $order->getData('billing_country_id') ?? '';
            $dto->billingAddress->telephone = (string) $order->getData('billing_telephone');
        } else {
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress && $billingAddress->getId()) {
                $dto->billingAddress = $this->addressMapper->fromOrderAddress($billingAddress);
            }
        }

        // For collection (batch) orders with joined data, skip expensive lazy-loads.
        $isCollectionOrder = $order->getData('billing_telephone') !== null;

        if (!$isCollectionOrder) {
            // Map shipping address (only for single-order detail views)
            $shippingAddress = $order->getShippingAddress();
            if ($shippingAddress && $shippingAddress->getId()) {
                $dto->shippingAddress = $this->addressMapper->fromOrderAddress($shippingAddress);
            }
        }

        // Map shipping method
        $dto->shippingMethod = $order->getShippingMethod();
        $dto->shippingDescription = $order->getShippingDescription();

        // Map payment method
        $payment = $order->getPayment();
        if ($payment) {
            $dto->paymentMethod = $payment->getMethod();
            if (!$isCollectionOrder) {
                try {
                    $dto->paymentMethodTitle = $payment->getMethodInstance()->getTitle();
                } catch (\Exception $e) {
                    $dto->paymentMethodTitle = $payment->getMethod();
                }
            } else {
                $dto->paymentMethodTitle = $payment->getMethod();
            }

            // Get change amount for cash payments
            $changeAmount = $payment->getAdditionalInformation('change_amount');
            if ($changeAmount !== null) {
                $dto->changeAmount = (float) $changeAmount;
            }
        }

        if (!$isCollectionOrder) {
            // Map status history (only for single-order detail views)
            $dto->statusHistory = $this->orderService->getOrderNotes($order);

            // Map shipments with tracking
            $dto->shipments = $this->mapShipmentsToDto($order);
        }

        \Mage::dispatchEvent('api_order_dto_build', ['order' => $order, 'dto' => $dto]);
        return $dto;
    }

    /**
     * Map Maho order item model to OrderItem DTO
     */
    public function mapItemToDto(\Mage_Sales_Model_Order_Item $item): OrderItem
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






        \Mage::dispatchEvent('api_order_item_dto_build', ['item' => $item, 'dto' => $dto]);
        return $dto;
    }

    /**
     * Map Maho order to prices array
     */
    private function mapPricesToArray(\Mage_Sales_Model_Order $order): array
    {
        $prices = [
            'subtotal' => (float) $order->getSubtotal(),
            'subtotalInclTax' => (float) $order->getSubtotalInclTax(),
            'discountAmount' => $order->getDiscountAmount()
                ? abs((float) $order->getDiscountAmount())
                : null,
            'shippingAmount' => $order->getShippingAmount()
                ? (float) $order->getShippingAmount()
                : null,
            'shippingAmountInclTax' => $order->getShippingInclTax()
                ? (float) $order->getShippingInclTax()
                : null,
            'taxAmount' => (float) $order->getTaxAmount(),
            'grandTotal' => (float) $order->getGrandTotal(),
            'totalPaid' => (float) $order->getTotalPaid(),
            'totalRefunded' => (float) $order->getTotalRefunded(),
            'totalDue' => (float) $order->getTotalDue(),
            'giftcardAmount' => null,
        ];

        $giftcardAmount = $order->getData('giftcard_amount');
        if ($giftcardAmount) {
            $prices['giftcardAmount'] = abs((float) $giftcardAmount);
        }

        return $prices;
    }

    /**
     * Map order shipments to Shipment DTOs
     *
     * @return Shipment[]
     */
    private function mapShipmentsToDto(\Mage_Sales_Model_Order $order): array
    {
        $shipments = [];

        foreach ($order->getShipmentsCollection() as $shipment) {
            $shipments[] = ShipmentMapper::mapToDto($shipment);
        }

        return $shipments;
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

        foreach ($collection as $payment) {
            $payments[] = $this->posPaymentMapper->mapToDto($payment);
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

        // Group payments by method
        $grouped = [];
        foreach ($collection as $payment) {
            $method = $payment->getMethodCode();
            if (!isset($grouped[$method])) {
                $grouped[$method] = [
                    'method' => $method,
                    'methodTitle' => PaymentService::getMethodLabel($method),
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
