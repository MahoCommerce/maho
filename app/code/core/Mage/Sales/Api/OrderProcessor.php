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
        // endpoint receives a storefront-shaped payload (shippingAddress,
        // billingAddress, paymentData, etc.) that doesn't map onto Order
        // fields. Parse the raw body so the placeOrder handler can read it.
        // GraphQL invocations already populate $context['args']['input'].
        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'placeOrder', '_api_/orders_post', 'place_guest_order', 'place_customer_order' => $this->placeOrder($context, $uriVariables),
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
        }

        return $order;
    }

    /**
     * Place order from cart. Accepts the cart identifier from the request body
     * (cartId / maskedId) OR from the URI (e.g. /guest-carts/{id}/place-order).
     * Also applies shipping/billing address, customer email, and payment-method
     * additionalInformation from the request body, storefront callers send the
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
        // Accept the masked-id from either the request body or from the URI.
        // We pull from the Request path rather than $uriVariables because API
        // Platform casts URI placeholders to the resource identifier's PHP
        // type, Order.id is int, so a 32-char hex masked id gets silently
        // truncated to its leading digit run via PHP (int) coercion. Parsing
        // the path ourselves preserves the string verbatim.
        $maskedId = $args['maskedId'] ?? null;
        if (!$maskedId) {
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $path = $request->getPathInfo();
                if (preg_match('#/guest-carts/([a-f0-9]{32})/place-order#i', $path, $m)) {
                    $maskedId = $m[1];
                }
            }
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

        // Get cart/quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        // Verify cart ownership
        $this->verifyCartOwnership($quote, $maskedId !== null);

        // Storefront callers send the full checkout state in the body, apply
        // any provided addresses to the quote before order placement so the
        // rate calculator and address validations see the right data.
        if (isset($args['shippingAddress']) && is_array($args['shippingAddress'])) {
            $this->cartService->setShippingAddress($quote, $this->mapPlaceOrderAddress($args['shippingAddress']));
        }
        if (isset($args['billingAddress']) && is_array($args['billingAddress'])) {
            $this->cartService->setBillingAddress($quote, $this->mapPlaceOrderAddress($args['billingAddress']));
        }

        // Set customer email from the body if provided (guest checkout)
        if ($guestEmail && \Mage::helper('core')->isValidEmail($guestEmail)) {
            $quote->setCustomerEmail($guestEmail);
        }

        // Set payment method + carry payment-method extras (e.g. Stripe
        // payment_intent_id) into the payment's additional_information so the
        // payment-method module can finalise the charge at order placement.
        if ($paymentMethod) {
            $payment = $quote->getPayment();
            $payment->setMethod($paymentMethod);
            if (isset($args['paymentData']) && is_array($args['paymentData'])) {
                foreach ($args['paymentData'] as $key => $value) {
                    $payment->setAdditionalInformation((string) $key, $value);
                }
            }
        }

        // Set shipping method directly on the in-memory address. The storefront
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
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        // Reject a method the client made up: after rates are collected the
        // chosen code must resolve to a real rate, otherwise a caller could
        // claim e.g. free shipping that the store does not actually offer.
        // Validate before persisting so a bogus method never lands on the saved
        // quote's shipping address (which would corrupt later loads of the cart).
        if ($validateShippingMethod && !$quote->getShippingAddress()->getShippingRateByCode($shippingMethod)) {
            throw new BadRequestHttpException('Shipping method is not available for this address');
        }

        $quote->save();

        // Allow modules to prepare the quote before order placement
        // (e.g. POS module sets default address, shipping, payment for admin orders)
        \Mage::dispatchEvent('sales_api_place_order_before', [
            'quote' => $quote,
            'payment_method' => $paymentMethod,
            'shipping_method' => $shippingMethod,
        ]);

        // Place order
        $result = $this->orderService->placeAdminOrder(
            $quote,
            $guestEmail,
            $orderNote,
            $cashTendered,
            $employeeId,
        );

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

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->addOrderNote($order, $comment, $notifyCustomer, $visibleOnFront);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Map a place-order address payload (camelCase, as sent by the storefront)
     * into the snake_case keys the legacy quote address model expects.
     */
    private function mapPlaceOrderAddress(array $input): array
    {
        return [
            'firstname' => $input['firstName'] ?? '',
            'lastname' => $input['lastName'] ?? '',
            'company' => $input['company'] ?? null,
            'street' => $input['street'] ?? [],
            'city' => $input['city'] ?? '',
            'region' => $input['region'] ?? '',
            'region_id' => $input['regionId'] ?? null,
            'postcode' => $input['postcode'] ?? '',
            'country_id' => $input['countryId'] ?? '',
            'telephone' => $input['telephone'] ?? '',
        ];
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
     * order from an arbitrary enumerable cart id. Closes the gap left by the
     * overridden process() bypassing the base Processor's requirePermission().
     */
    private function isPrivilegedOrderActor(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return $this->isApiUser() && $this->getAuthorizedUser()->hasPermission('orders/create');
    }

}
