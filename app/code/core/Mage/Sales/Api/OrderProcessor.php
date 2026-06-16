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
 * Order State Processor - Handles order mutations for API Platform
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

        // Bridge raw REST body → context args. API Platform deserialises POST
        // bodies into the resource DTO (Order here), but the place-order
        // endpoint receives a storefront-shaped payload (shippingAddress,
        // billingAddress, paymentData, etc.) that doesn't map onto Order
        // fields. Parse the raw body so the placeOrder handler can read it.
        // GraphQL invocations already populate $context['args']['input'].
        if (empty($context['args']['input'])) {
            $context['args']['input'] = [];
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $body = json_decode($request->getContent(), true);
                if (is_array($body)) {
                    $context['args']['input'] = $body;
                }
            }
        }

        return match ($operationName) {
            'placeOrder', '_api_/orders_post', 'place_guest_order' => $this->placeOrder($context, $uriVariables),
            'cancelOrder' => $this->cancelOrder($context),
            default => $data instanceof Order ? $data : new Order(),
        };
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
        $isPrivileged = $this->isAdmin() || $this->isApiUser();
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
        if ($guestEmail && filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
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
        $quote->save();

        // Reject a method the client made up: after rates are collected the
        // chosen code must resolve to a real rate, otherwise a caller could
        // claim e.g. free shipping that the store does not actually offer.
        if ($validateShippingMethod && !$quote->getShippingAddress()->getShippingRateByCode($shippingMethod)) {
            throw new BadRequestHttpException('Shipping method is not available for this address');
        }

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
    private function cancelOrder(array $context): Order
    {
        $args = $context['args']['input'] ?? [];
        $orderId = $args['orderId'] ?? null;
        $incrementId = $args['incrementId'] ?? null;
        $reason = $args['reason'] ?? null;

        // Get order
        $order = $this->orderService->getOrder(
            $orderId ? (int) $orderId : null,
            $incrementId,
        );

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify access: customers can only cancel their own orders
        if (!$this->isAdmin() && !$this->isApiUser()) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new BadRequestHttpException('Authentication required to cancel orders');
            }
            if ((int) $order->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Order not found');
            }
        }

        // Cancel order
        $order = $this->orderService->cancelOrder($order, $reason);

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
            $this->isAdmin() || $this->isApiUser(),
        );
    }

}
