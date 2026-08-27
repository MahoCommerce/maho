<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Checkout\Api\CartService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Order State Processor - Handles order mutations for API Platform.
 */
final class OrderProcessor extends \Maho\ApiPlatform\Processor
{
    private CartService $cartService;
    private OrderProvider $orderProvider;
    private OrderService $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartService = new CartService();
        $this->orderProvider = new OrderProvider($security);
        $this->orderService = new OrderService();
    }

    /**
     * Process order mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        $operationName = $operation->getName();

        // Bridge raw REST body into context args. API Platform deserialises POST
        // bodies into the resource DTO (Order here), but the place-order
        // endpoint receives a frontend-shaped payload (shippingAddress,
        // billingAddress, paymentData, etc.) that doesn't map onto Order
        // fields. Parse the raw body so the placeOrder handler can read it.
        // GraphQL invocations already populate $context['args']['input'].
        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'place', 'place_order', 'place_guest_order', 'place_customer_order' => $this->placeOrder($context, $uriVariables),
            'cancel', 'order_cancel' => $this->cancelOrder($context, $uriVariables),
            'hold', 'order_hold' => $this->holdOrder($context, $uriVariables),
            'unhold', 'order_unhold' => $this->unholdOrder($context, $uriVariables),
            'addComment', 'order_add_comment' => $this->addOrderComment($context, $uriVariables),
            default => $data instanceof Order ? $data : new Order(),
        };
    }

    /**
     * Resolve the target order from the request: numeric {id} in the REST URI,
     * or orderId / incrementId in the GraphQL/body args. Enforces that a plain
     * customer caller owns the order; admin and orders/write service tokens are
     * trusted (gated upstream by the operation security expression).
     */
    private function resolveManagedOrder(array $context, array $uriVariables): \Mage_Sales_Model_Order
    {
        $args = $context['args']['input'] ?? [];
        $orderId = $uriVariables['id'] ?? $args['orderId'] ?? null;
        $incrementId = $args['incrementId'] ?? null;

        $order = $this->orderService->getOrder(
            $orderId !== null ? (int) $orderId : null,
            $incrementId,
        );
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        if (!$this->isAdmin() && !$this->isApiUser()) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId || (int) $order->getCustomerId() !== $customerId) {
                // Don't disclose existence to a non-owner.
                throw new NotFoundHttpException('Order not found');
            }
        } else {
            $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');
        }

        return $order;
    }

    /**
     * Place order from cart. Accepts the cart identifier from the request body
     * (cartId / maskedId) OR from the URI (e.g. /guest-carts/{id}/place-order).
     * Also applies shipping/billing address, customer email, and payment-method
     * additionalInformation from the request body, frontend callers send the
     * full checkout state in one shot rather than pre-mutating the cart.
     */
    private function placeOrder(array $context, array $uriVariables = []): Order
    {
        $args = $context['args']['input'] ?? $context['request_data'] ?? [];
        $cartId = $args['cartId'] ?? null;
        // Recover the numeric cart id from the authenticated /carts/{id}/place-order
        // path when it wasn't supplied in the body. Ownership is enforced below by
        // verifyCartOwnership() (accessedByMaskedId=false → customer-ownership check).
        if (!$cartId) {
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request
                && preg_match('#/carts/(\d+)/place-order#', $request->getPathInfo(), $cm)) {
                $cartId = $cm[1];
            }
        }
        // Accept the masked id from the URI, else from the request body. We pull
        // from the Request path rather than $uriVariables because API Platform
        // casts URI placeholders to the resource identifier's PHP type, Order.id
        // is int, so a 32-char hex masked id gets silently truncated to its
        // leading digit run via PHP (int) coercion. Parsing the path ourselves
        // preserves the string verbatim. The path wins over the body so that a
        // body id can never place the order of a cart the URI does not name.
        $request = $context['request'] ?? null;
        $maskedId = $request instanceof \Symfony\Component\HttpFoundation\Request
            ? CartService::maskedIdFromPath($request->getPathInfo())
            : null;
        if (!$maskedId && is_string($args['maskedId'] ?? null)) {
            $maskedId = $args['maskedId'];
        }
        $guestEmail = $args['guestEmail'] ?? $args['email'] ?? null;
        $orderNote = $args['orderNote'] ?? null;
        // POS-only fields: only trust them from admin/api callers so a guest
        // cannot stamp an arbitrary employee id or cash amount onto the order.
        $isPrivileged = $this->isPrivilegedOrderActor();
        $cashTendered = ($isPrivileged && isset($args['cashTendered'])) ? (float) $args['cashTendered'] : null;
        $employeeId = ($isPrivileged && isset($args['employeeId'])) ? (int) $args['employeeId'] : null;
        $paymentMethod = $args['paymentMethod'] ?? null;
        $shippingMethod = $args['shippingMethod'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        // Verify cart ownership
        $this->verifyCartOwnership($quote, $maskedId !== null);

        // Frontend callers send the full checkout state in the body. Apply any
        // provided addresses in-memory; the single collection below prices them
        // together with the shipping method, and the final save persists them.
        if (isset($args['shippingAddress']) && is_array($args['shippingAddress'])) {
            $this->cartService->applyShippingAddress($quote, $this->cartService->mapAddressInput($args['shippingAddress']));
        }
        if (isset($args['billingAddress']) && is_array($args['billingAddress'])) {
            $this->cartService->applyBillingAddress($quote, $this->cartService->mapAddressInput($args['billingAddress']));
        }

        // Set customer email from the body if provided (guest checkout)
        if ($guestEmail && \Mage::helper('core')->isValidEmail($guestEmail)) {
            $quote->setCustomerEmail($guestEmail);
        }

        // Set shipping method directly on the in-memory address. The frontend
        // sends a composite carrier_method string in the body, and we preserve
        // the in-memory quote state through to placeAdminOrder rather than
        // save + reload through cartService->setShippingMethod.
        $validateShippingMethod = false;
        if ($shippingMethod && !$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod($shippingMethod);
            $shippingAddress->setCollectShippingRates(1);
            $validateShippingMethod = true;
        }
        // Collect once: this prices the applied addresses and shipping method,
        // and gives the payment gate below fresh totals
        CartService::collectAndVerifyTotals($quote);

        // Reject a method the client made up: after rates are collected the
        // chosen code must resolve to a real rate, otherwise a caller could
        // claim e.g. free shipping that the store does not actually offer.
        // Validate before persisting so a bogus method never lands on the saved
        // quote's shipping address (which would corrupt later loads of the cart).
        if ($validateShippingMethod) {
            $rate = $quote->getShippingAddress()->getShippingRateByCode($shippingMethod);
            // A carrier failure produces a rate whose code is the carrier's
            // error entry; it must not be orderable (it would price at 0)
            if (!$rate || $rate->getErrorMessage()) {
                throw new BadRequestHttpException('Shipping method is not available for this address');
            }
        }

        // Gate the method pre-fee: setMethod() alone accepts any configured
        // code, so without this a client could force e.g. "free" on a paid
        // cart and place an unpaid order.
        if ($paymentMethod) {
            $this->cartService->assertPaymentMethodAvailable($quote, $paymentMethod);

            $paymentData = CartService::buildPaymentImportData(
                $paymentMethod,
                (isset($args['paymentData']) && is_array($args['paymentData'])) ? $args['paymentData'] : null,
                $isPrivileged
                    ? \Mage_Payment_Model_Method_Abstract::CHECKS_INTERNAL
                    : \Mage_Payment_Model_Method_Abstract::CHECKS_CHECKOUT,
            );
            try {
                $quote->getPayment()->importData($paymentData);
            } catch (\Exception $e) {
                throw new BadRequestHttpException('Payment method is not available: ' . $e->getMessage());
            }

            // Recollect so payment-dependent totals (e.g. payment fees) land on
            // the order; the consumed rates flag keeps the validated rates.
            CartService::collectAndVerifyTotals($quote);
        }

        $placeOrder = function () use ($quote, $paymentMethod, $shippingMethod, $guestEmail, $orderNote, $cashTendered, $employeeId): array {
            $quote->save();

            // Allow modules to prepare the quote before order placement
            // (e.g. POS module sets default address, shipping, payment for admin orders)
            \Mage::dispatchEvent('sales_api_place_order_before', [
                'quote' => $quote,
                'payment_method' => $paymentMethod,
                'shipping_method' => $shippingMethod,
            ]);

            return $this->orderService->placeAdminOrder(
                $quote,
                $guestEmail,
                $orderNote,
                $cashTendered,
                $employeeId,
            );
        };

        // An admin-scoped caller places in admin scope so payment methods apply
        // MOTO handling (issue #1337). Any other caller places in the quote's
        // store scope, so store-scoped inventory config (can_subtract,
        // backorders) and save observers resolve against the order's own store
        // even when the caller's X-Store-Code names a different one.
        $result = \Mage::app()->getStore()->isAdmin()
            ? $placeOrder()
            : CartService::inQuoteStoreScope($quote, $placeOrder);

        $order = $result['order'];
        $accessToken = $result['accessToken'];
        $changeAmount = $result['changeAmount'];

        $dto = $this->orderProvider->mapToDto($order, $accessToken);
        if ($changeAmount !== null) {
            $dto->changeAmount = $changeAmount;
        }
        return $dto;
    }

    /**
     * Cancel order
     */
    private function cancelOrder(array $context, array $uriVariables = []): Order
    {
        $args = $context['args']['input'] ?? [];
        $reason = $args['reason'] ?? null;

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->cancelOrder($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Put an order on hold (admin / orders-write only).
     */
    private function holdOrder(array $context, array $uriVariables = []): Order
    {
        $reason = $context['args']['input']['reason'] ?? null;

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->holdOrder($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Release an order from hold (admin / orders-write only).
     */
    private function unholdOrder(array $context, array $uriVariables = []): Order
    {
        $reason = $context['args']['input']['reason'] ?? null;

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->unholdOrder($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Add a status-history comment to an order (admin / orders-write only).
     * An optional status is validated against the statuses assigned to the
     * order's current state, the same constraint the admin comment form
     * enforces, and applied via addStatusHistoryComment().
     */
    private function addOrderComment(array $context, array $uriVariables = []): Order
    {
        $args = $context['args']['input'] ?? [];
        $comment = trim((string) ($args['comment'] ?? $args['note'] ?? ''));
        if ($comment === '') {
            throw new BadRequestHttpException('Comment text is required');
        }
        $notifyCustomer = (bool) ($args['notifyCustomer'] ?? false);
        $visibleOnFront = (bool) ($args['visibleOnFront'] ?? false);
        $status = $args['status'] ?? null;
        if ($status !== null && !is_string($status)) {
            throw new BadRequestHttpException('Status must be a string');
        }
        $status = $status !== null ? trim($status) : null;
        if ($status === '') {
            $status = null;
        }

        $order = $this->resolveManagedOrder($context, $uriVariables);
        if ($status !== null) {
            $allowed = $order->getConfig()->getStateStatuses($order->getState(), false);
            if (!in_array($status, $allowed, true)) {
                throw new BadRequestHttpException(sprintf(
                    'Status "%s" is not assigned to the order\'s current state "%s"; allowed: %s',
                    $status,
                    (string) $order->getState(),
                    implode(', ', $allowed),
                ));
            }
        }
        $order = $this->orderService->addOrderNote($order, $comment, $notifyCustomer, $visibleOnFront, $status);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Verify the current user has access to place an order for this cart
     */
    private function verifyCartOwnership(\Mage_Sales_Model_Quote $quote, bool $accessedByMaskedId): void
    {
        $this->cartService->verifyCartAccess(
            $quote,
            $accessedByMaskedId,
            $this->getAuthenticatedCustomerId(),
            $this->isPrivilegedOrderActor(),
        );
    }

    /**
     * Whether the caller may place/manage an order on any cart, bypassing
     * ownership. Admins are gated upstream by AdminAclListener
     * (Order::ADMIN_RESOURCE); a service token is trusted only when it holds the
     * orders/create grant. A bare service-account token without it stays subject
     * to the guest masked-id / customer-ownership rules, so it can't place an
     * order from an arbitrary enumerable cart id, even on the public guest
     * operations whose security expression admits everyone.
     */
    private function isPrivilegedOrderActor(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return $this->isApiUser() && $this->requireUser()->hasPermission('orders/create');
    }

}
