<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Mage\Customer\Api\Address;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Order State Provider - Fetches order data for API Platform.
 */
final class OrderProvider extends \Maho\ApiPlatform\Provider
{
    private OrderService $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->orderService = new OrderService();
    }

    /**
     * Provide order data based on operation type
     *
     * @return Order|TraversablePaginator<Order>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Order|TraversablePaginator|null
    {
        $operationName = $operation->getName();

        // Handle REST /customers/me/orders endpoint
        if ($operationName === 'my_orders') {
            return $this->getMyOrders($context);
        }

        // Handle REST collection endpoint (GET /orders)
        if ($operation instanceof CollectionOperationInterface && !in_array($operationName, ['customer', 'my_orders'])) {
            return $this->getCollection($context);
        }

        // Handle REST guest-order read via X-Order-Token header.
        // Mirrors the GraphQL `guestOrder` query but consumes (clears) the
        // token on a successful read so refreshing the page can't replay
        // analytics or expose the order to a later viewer.
        if ($operationName === 'get_order_by_token') {
            // Public, unauthenticated endpoint: throttle by IP so the strong
            // one-time token can't be brute-forced against a known increment ID.
            $this->checkRateLimitByIp('guest_order_token', 'guest_order_lookup', 3600);

            $request = $context['request'] ?? null;
            $token = '';
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                // Header only: a token in the query string would leak into web
                // server / CDN access logs and Referer headers, surviving the
                // one-time-use wipe below for the lifetime of those logs.
                $token = (string) $request->headers->get('X-Order-Token', '');
            }
            $incrementId = (string) ($uriVariables['incrementId'] ?? '');

            if ($token === '' || $incrementId === '') {
                return null;
            }

            $order = $this->orderService->getGuestOrder($incrementId, $token);
            if (!$order) {
                return null;
            }

            $dto = $this->mapToDto($order);

            // Issue an account-creation token if no customer exists for this email
            $orderEmail = $order->getCustomerEmail();
            if ($orderEmail) {
                $existingCustomer = \Mage::getModel('customer/customer')
                    ->setWebsiteId(\Mage::app()->getStore($order->getStoreId())->getWebsiteId())
                    ->loadByEmail($orderEmail);

                if (!$existingCustomer->getId()) {
                    $dto->accountToken = AccountTokenService::generate((int) $order->getId(), $orderEmail);
                }
            }

            // One-time use: clear the token so refreshing the success page
            // doesn't re-fire analytics or re-expose the order. Done only after
            // the DTO is built so a mapping failure can't permanently strand the
            // guest's access to their order.
            $resource = \Mage::getSingleton('core/resource');
            $resource->getConnection('core_write')->update(
                $resource->getTableName('sales/order'),
                ['guest_access_token' => null],
                ['entity_id = ?' => $order->getId()],
            );

            return $dto;
        }

        // Handle guestOrder query - get order by increment ID and access token
        if ($operationName === 'guest') {
            // Same public token-lookup surface as the REST path above; throttle
            // by IP so the one-time token can't be brute-forced via GraphQL.
            $this->checkRateLimitByIp('guest_order_token', 'guest_order_lookup', 3600);

            $incrementId = $context['args']['incrementId'] ?? null;
            $accessToken = $context['args']['accessToken'] ?? null;

            if (!$incrementId || !$accessToken) {
                return null;
            }

            $order = $this->orderService->getGuestOrder($incrementId, $accessToken);
            if (!$order) {
                return null;
            }

            // Don't echo the access token back: it's consumed below (one-time
            // use), so returning it would only leak a dead token into response
            // bodies, logs and caches. Mirrors the REST get_order_by_token path.
            $dto = $this->mapToDto($order);

            // Issue an account-creation token if no customer exists for this email
            $orderEmail = $order->getCustomerEmail();
            if ($orderEmail) {
                $existingCustomer = \Mage::getModel('customer/customer')
                    ->setWebsiteId(\Mage::app()->getStore($order->getStoreId())->getWebsiteId())
                    ->loadByEmail($orderEmail);

                if (!$existingCustomer->getId()) {
                    $dto->accountToken = AccountTokenService::generate((int) $order->getId(), $orderEmail);
                }
            }

            // One-time use: clear the token so it can't be replayed to re-expose
            // the order. Mirrors the REST get_order_by_token path; done only after
            // the DTO is built so a mapping failure can't strand the guest.
            $resource = \Mage::getSingleton('core/resource');
            $resource->getConnection('core_write')->update(
                $resource->getTableName('sales/order'),
                ['guest_access_token' => null],
                ['entity_id = ?' => $order->getId()],
            );

            return $dto;
        }

        // Handle customerOrders collection query
        if ($operationName === 'customer') {
            // Bind strictly to the authenticated JWT identity (mirrors getMyOrders).
            // Never trust a request/context-supplied id here, that would be an IDOR.
            $customerId = $this->getAuthenticatedCustomerId();
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

        // Store allowlist enforcement for back-office tokens; customer tokens
        // are identity-bound above and stay untouched.
        if ($this->isAdmin() || $this->isApiUser()) {
            $this->assertStoreAllowed($order->getStoreId(), $this->getAuthorizedUser(), 'order');
        }

        return $this->mapToDto($order);
    }

    /**
     * Check if current user can access the given order
     *
     * - Admins: full access
     * - API service accounts: full access (permission already checked by the operation security expression)
     * - Customers: own orders only
     */
    private function canAccessOrder(\Mage_Sales_Model_Order $order): bool
    {
        // Admins can access any order
        if ($this->isAdmin()) {
            return true;
        }

        // API users with orders/read permission can access any order. The
        // granular orders/read check is enforced upstream by the operation's
        // `security: is_granted('orders/read')` expression (via ApiUserVoter)
        // before this runs, so by the time we get here the key is already
        // authorized to read orders.
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
<<<<<<< HEAD
        $filters = $context['filters'] ?? [];
        $status = $filters['status'] ?? null;
        $email = $filters['email'] ?? null;
        $emailLike = $filters['emailLike'] ?? null;
        $incrementId = $filters['incrementId'] ?? null;
        $since = $filters['since'] ?? null;

        $result = $this->orderService->getAllOrders($page, $pageSize, $status, $email, $incrementId, $emailLike, $since);
=======
        $result = $this->orderService->getAllOrders(
            $page,
            $pageSize,
            $context['filters'] ?? [],
            $this->getAuthorizedUser()->getAllowedStoreIds(),
        );
>>>>>>> 46dc60e (Added missing REST/GraphQL API fields, operations, and store-scoped reads/writes across all resources (#1210))

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
        $dto->customerMiddlename = $order->getCustomerMiddlename();
        $dto->customerPrefix = $order->getCustomerPrefix();
        $dto->customerSuffix = $order->getCustomerSuffix();
        $dto->customerTaxvat = $order->getCustomerTaxvat();
        $dto->customerDob = $order->getCustomerDob();
        $dto->customerGender = $order->getCustomerGender() !== null ? (int) $order->getCustomerGender() : null;
        $dto->customerGroupId = $order->getCustomerGroupId() !== null ? (int) $order->getCustomerGroupId() : null;
        $dto->customerIsGuest = (bool) $order->getCustomerIsGuest();
        $dto->customerNote = $order->getCustomerNote();
        $dto->status = $order->getStatus();
        $dto->state = $order->getState();
        $dto->holdBeforeState = $order->getHoldBeforeState();
        $dto->holdBeforeStatus = $order->getHoldBeforeStatus();
        $dto->storeId = (int) $order->getStoreId();
        $dto->storeName = $order->getStoreName();
        $dto->quoteId = $order->getQuoteId() ? (int) $order->getQuoteId() : null;
        $dto->isVirtual = (bool) $order->getIsVirtual();
        $dto->weight = $order->getWeight() !== null ? (float) $order->getWeight() : null;
        $dto->emailSent = (bool) $order->getEmailSent();
        $dto->extOrderId = $order->getExtOrderId();
        $dto->extCustomerId = $order->getExtCustomerId();
        $dto->currency = $order->getOrderCurrencyCode() ?: \Mage::app()->getStore()->getDefaultCurrencyCode();
        $dto->baseCurrencyCode = $order->getBaseCurrencyCode();
        $dto->globalCurrencyCode = $order->getGlobalCurrencyCode();
        $dto->totalItemCount = (int) $order->getTotalItemCount();
        $dto->totalQtyOrdered = (float) $order->getTotalQtyOrdered();
        $dto->createdAt = $order->getCreatedAt();
        $dto->updatedAt = $order->getUpdatedAt();
        $dto->couponCode = $order->getCouponCode();
        $dto->couponRuleName = $order->getCouponRuleName();
        $dto->discountDescription = $order->getDiscountDescription();
        $dto->appliedRuleIds = $this->parseAppliedRuleIds($order->getAppliedRuleIds());
        $dto->giftcardCodes = $this->parseGiftcardCodes($order->getData('giftcard_codes'));

        // Fraud-relevant request metadata is for back-office eyes only, never
        // echoed to customer or guest-token readers.
        if ($this->isAdmin() || $this->isApiUser()) {
            $dto->remoteIp = $order->getRemoteIp();
            $dto->xForwardedFor = $order->getXForwardedFor();
        }

        // Set access token for guest orders
        if ($accessToken) {
            $dto->accessToken = $accessToken;
        }

        // Map items, use preloaded items if available (batch-loaded), otherwise load.
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

        // Map billing address, use joined data if available, otherwise load.
        if ($order->getData('billing_telephone') !== null) {
            $dto->billingAddress = new Address();
            $dto->billingAddress->id = (int) ($order->getData('billing_addr_id') ?: 0);
            $dto->billingAddress->firstname = $order->getData('billing_firstname') ?? '';
            $dto->billingAddress->lastname = $order->getData('billing_lastname') ?? '';
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
                $dto->billingAddress = Address::fromOrderAddress($billingAddress);
            }
        }

        // For collection (batch) orders with joined data, skip expensive lazy-loads.
        $isCollectionOrder = $order->getData('billing_telephone') !== null;

        if (!$isCollectionOrder) {
            // Map shipping address (only for single-order detail views)
            $shippingAddress = $order->getShippingAddress();
            if ($shippingAddress && $shippingAddress->getId()) {
                $dto->shippingAddress = Address::fromOrderAddress($shippingAddress);
            }
        }

        // Map shipping method
        $dto->shippingMethod = $order->getShippingMethod();
        $dto->shippingDescription = $order->getShippingDescription();

        // Map payment method. List paths batch-load payments in
        // OrderService::paginateAndPreload(); fall back to the lazy per-order
        // load only for single-order views.
        $payment = $order->getData('_preloaded_payment') ?? $order->getPayment();
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
        $dto->description = $item->getDescription();
        $dto->qtyInvoiced = (float) $item->getQtyInvoiced();
        $dto->qtyBackordered = $item->getQtyBackordered() !== null ? (float) $item->getQtyBackordered() : null;
        $dto->originalPrice = $item->getOriginalPrice() !== null ? (float) $item->getOriginalPrice() : null;
        $dto->basePrice = (float) $item->getBasePrice();
        $dto->baseRowTotal = (float) $item->getBaseRowTotal();
        $dto->baseTaxAmount = $item->getBaseTaxAmount() !== null ? (float) $item->getBaseTaxAmount() : null;
        $dto->baseDiscountAmount = $item->getBaseDiscountAmount() !== null ? (float) $item->getBaseDiscountAmount() : null;
        $dto->baseCost = $item->getBaseCost() !== null ? (float) $item->getBaseCost() : null;
        $dto->amountRefunded = $item->getAmountRefunded() !== null ? (float) $item->getAmountRefunded() : null;
        $dto->taxRefunded = $item->getTaxRefunded() !== null ? (float) $item->getTaxRefunded() : null;
        $dto->discountRefunded = $item->getDiscountRefunded() !== null ? (float) $item->getDiscountRefunded() : null;
        $dto->weight = $item->getWeight() !== null ? (float) $item->getWeight() : null;
        $dto->rowWeight = $item->getRowWeight() !== null ? (float) $item->getRowWeight() : null;
        $dto->isVirtual = (bool) $item->getIsVirtual();
        $dto->isQtyDecimal = (bool) $item->getIsQtyDecimal();
        $dto->freeShipping = (bool) $item->getFreeShipping();
        $dto->noDiscount = (bool) $item->getNoDiscount();
        $dto->additionalData = $item->getAdditionalData();
        $dto->extOrderItemId = $item->getExtOrderItemId();
        $dto->storeId = $item->getStoreId() ? (int) $item->getStoreId() : null;
        $dto->createdAt = $item->getCreatedAt();
        // unserialize() hands back the raw value when a legacy row holds neither
        // JSON nor a serialized payload, so never trust it to be an array.
        $productOptions = $item->getProductOptions();
        $dto->productOptions = is_array($productOptions) ? $productOptions : [];

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
            'shippingTaxAmount' => $order->getShippingTaxAmount() !== null
                ? (float) $order->getShippingTaxAmount()
                : null,
            'hiddenTaxAmount' => $order->getHiddenTaxAmount() !== null
                ? (float) $order->getHiddenTaxAmount()
                : null,
            'shippingDiscountAmount' => $order->getShippingDiscountAmount() !== null
                ? (float) $order->getShippingDiscountAmount()
                : null,
            'grandTotal' => (float) $order->getGrandTotal(),
            'totalPaid' => (float) $order->getTotalPaid(),
            'totalRefunded' => (float) $order->getTotalRefunded(),
            'totalDue' => (float) $order->getTotalDue(),
            'totalCanceled' => $order->getTotalCanceled() !== null ? (float) $order->getTotalCanceled() : null,
            'totalInvoiced' => $order->getTotalInvoiced() !== null ? (float) $order->getTotalInvoiced() : null,
            'subtotalCanceled' => $order->getSubtotalCanceled() !== null ? (float) $order->getSubtotalCanceled() : null,
            'subtotalInvoiced' => $order->getSubtotalInvoiced() !== null ? (float) $order->getSubtotalInvoiced() : null,
            'subtotalRefunded' => $order->getSubtotalRefunded() !== null ? (float) $order->getSubtotalRefunded() : null,
            'adjustmentPositive' => $order->getAdjustmentPositive() !== null
                ? (float) $order->getAdjustmentPositive()
                : null,
            'adjustmentNegative' => $order->getAdjustmentNegative() !== null
                ? (float) $order->getAdjustmentNegative()
                : null,
            'baseGrandTotal' => (float) $order->getBaseGrandTotal(),
            'baseSubtotal' => (float) $order->getBaseSubtotal(),
            'baseTaxAmount' => (float) $order->getBaseTaxAmount(),
            'baseShippingAmount' => $order->getBaseShippingAmount() !== null
                ? (float) $order->getBaseShippingAmount()
                : null,
            'baseDiscountAmount' => $order->getBaseDiscountAmount()
                ? abs((float) $order->getBaseDiscountAmount())
                : null,
            'baseTotalPaid' => (float) $order->getBaseTotalPaid(),
            'baseTotalRefunded' => (float) $order->getBaseTotalRefunded(),
            'baseTotalDue' => (float) $order->getBaseTotalDue(),
            'giftcardAmount' => null,
        ];

        $giftcardAmount = $order->getData('giftcard_amount');
        if ($giftcardAmount) {
            $prices['giftcardAmount'] = abs((float) $giftcardAmount);
        }

        return $prices;
    }

    /**
     * @return int[]
     */
    private function parseAppliedRuleIds(?string $ruleIds): array
    {
        if (!$ruleIds) {
            return [];
        }

        return array_values(array_map(
            'intval',
            array_filter(array_map('trim', explode(',', $ruleIds)), fn(string $id): bool => $id !== ''),
        ));
    }

    /**
     * Decode the giftcard_codes column ({"CODE": appliedAmount} JSON, copied
     * verbatim from the quote at conversion).
     *
     * @return array<string, float>
     */
    private function parseGiftcardCodes(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = \Mage::helper('core')->jsonDecode($raw);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $codes = [];
        foreach ($decoded as $code => $amount) {
            $codes[(string) $code] = (float) $amount;
        }

        return $codes;
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
            $shipments[] = Shipment::fromModel($shipment);
        }

        return $shipments;
    }

}
