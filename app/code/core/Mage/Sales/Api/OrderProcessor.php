<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use Mage\Checkout\Api\CartRequest;
use Mage\Customer\Api\CustomAttributes;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Order State Processor - Handles order mutations for API Platform.
 */
final class OrderProcessor extends \Maho\ApiPlatform\Processor
{
    /** The fields of guestCustomer in a place-order body, each with its cart column. */
    private const GUEST_CUSTOMER_FIELDS = [
        'prefix' => 'customer_prefix',
        'firstname' => 'customer_firstname',
        'middlename' => 'customer_middlename',
        'lastname' => 'customer_lastname',
        'suffix' => 'customer_suffix',
        'dob' => 'customer_dob',
        'taxvat' => 'customer_taxvat',
        'gender' => 'customer_gender',
    ];

    private \Mage_Checkout_Service_Cart $cartService;
    private OrderProvider $orderProvider;
    private \Mage_Sales_Service_Order $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartService = \Mage::getService('checkout/cart');
        $this->orderProvider = new OrderProvider($security);
        $this->orderService = \Mage::getService('sales/order');
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
            'place', 'place_order', 'place_guest_order', 'place_customer_order' => $this->placeOrder($uriVariables, $context),
            'cancel', 'order_cancel' => $this->cancelOrder($context, $uriVariables),
            'hold', 'order_hold' => $this->holdOrder($context, $uriVariables),
            'unhold', 'order_unhold' => $this->unholdOrder($context, $uriVariables),
            'addComment', 'order_add_comment' => $this->addOrderComment($context, $uriVariables),
            default => $data instanceof Order ? $data : new Order(),
        };
    }

    /**
     * Resolve the target order from the request: numeric {id} in the REST URI,
     * or orderId / incrementId in the GraphQL/body args. A customer finds only
     * the orders that the storefront shows them; admin and orders/write service
     * tokens are trusted (gated upstream by the operation security expression).
     */
    private function resolveManagedOrder(array $context, array $uriVariables): \Mage_Sales_Model_Order
    {
        $input = RequestInput::of($context['args']['input'] ?? []);
        $orderId = (int) ($uriVariables['id'] ?? $input->int('orderId') ?? 0);
        $incrementId = $input->string('incrementId');
        $byId = $orderId > 0 || $incrementId === null;

        if (!$this->isAdmin() && !$this->isApiUser()) {
            $customerId = $this->getAuthenticatedCustomerId();
            return $byId
                ? $this->orderService->requireVisibleToCustomer($orderId, $customerId)
                : $this->orderService->requireVisibleToCustomerByIncrementId($incrementId, $customerId);
        }

        $order = $byId ? $this->orderService->getById($orderId) : $this->orderService->getByIncrementId($incrementId);
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        return $order;
    }

    /**
     * Write the fields of $values, the guestCustomer of the body, into the customer columns of $quote.
     */
    private function applyGuestCustomer(\Mage_Sales_Model_Quote $quote, mixed $values): void
    {
        if ($values === null) {
            return;
        }
        if (!is_array($values)) {
            throw new BadRequestHttpException('guestCustomer must be an object');
        }
        foreach (self::GUEST_CUSTOMER_FIELDS as $field => $column) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            if ($values[$field] !== null && !is_scalar($values[$field])) {
                throw new BadRequestHttpException("guestCustomer.{$field} must be a single value");
            }
            $value = $values[$field] === null ? null : trim((string) $values[$field]);
            $quote->setData($column, $value === '' ? null : $value);
        }
    }

    /**
     * Place order from cart. CartRequest::resolve() finds the cart that the URI
     * (e.g. /guest-carts/{id}/place-order) or the request body (cartId / maskedId) names.
     * The body can hold the addresses, the shipping method and the payment data of the order:
     * \Mage_Checkout_Service_Cart::applyCheckoutState() applies them before the order is placed.
     */
    private function placeOrder(array $uriVariables, array $context): Order
    {
        $args = $context['args']['input'] ?? $context['request_data'] ?? [];
        $input = RequestInput::of($args);
        $guestEmail = $input->string('guestEmail', 'email');
        $orderNote = $input->string('orderNote');
        $isPrivileged = $this->isPrivilegedOrderActor();
        $actor = $isPrivileged ? \Mage_Checkout_Model_Actor::Staff : \Mage_Checkout_Model_Actor::Customer;
        $agreementIds = $actor === \Mage_Checkout_Model_Actor::Customer ? $input->intList('agreementIds') ?? [] : null;
        $sendConfirmation = $isPrivileged ? $input->bool('sendConfirmation') : null;
        $paymentMethod = $input->string('paymentMethod');
        $shippingMethod = $input->string('shippingMethod');
        $paymentData = $input->array('paymentData');
        // Scope, not authorization: a caller in the admin scope (store 0) places a backend
        // order, while a service token naming a real store is a storefront flow.
        $isAdminOrder = \Mage::app()->getStore()->isAdmin();

        ['quote' => $quote, 'accessedByMaskedId' => $accessedByMaskedId] = CartRequest::resolve($uriVariables, $context);
        $quote = $this->cartService->requireAccessible(
            $quote,
            $accessedByMaskedId,
            $this->getAuthenticatedCustomerId(),
            $isPrivileged,
        );

        // A guest sends the fields of the Checkout Registration form, which a customer order takes from the account
        if (!$quote->getCustomerId()) {
            $this->applyGuestCustomer($quote, $args['guestCustomer'] ?? null);
            CustomAttributes::applyToGuestQuote($quote, $args['customerCustomAttributes'] ?? null, $isAdminOrder);
        } elseif (isset($args['customerCustomAttributes'])) {
            throw new \Mage_Core_Exception_Input()->addError('customer_custom_attributes', 'customerCustomAttributes is for a guest order. A customer order takes the values of the customer account');
        }

        // The client sends the checkout data with the order, and the cart service applies it in one pass
        $addresses = [];
        foreach (['shippingAddress' => $quote->getShippingAddress(), 'billingAddress' => $quote->getBillingAddress()] as $key => $address) {
            $values = $input->array($key);
            if ($values === null) {
                $addresses[$key] = null;
                continue;
            }
            CustomAttributes::applyToCartAddress($address, $values['customAttributes'] ?? null);
            $addresses[$key] = CartRequest::mapAddress($values, $input->pathOf($key));
        }
        try {
            $this->cartService->applyCheckoutState(
                $quote,
                $addresses['shippingAddress'],
                $addresses['billingAddress'],
                $shippingMethod,
                $paymentMethod,
                $paymentData,
                CartRequest::backOfficeActor($isPrivileged),
            );
        } catch (\Mage_Core_Exception_Input $e) {
            throw CartRequest::withAddressFieldNames($e, ['shipping_address' => 'shippingAddress', 'billing_address' => 'billingAddress']);
        }

        $placeOrder = fn(): array => $this->orderService->place(
            $quote,
            $guestEmail,
            $orderNote,
            $actor,
            $agreementIds,
            $sendConfirmation,
        );

        // An admin-scoped caller places in admin scope so payment methods apply
        // MOTO handling (issue #1337). Any other caller places in the quote's
        // store scope, so store-scoped inventory config (can_subtract,
        // backorders) and save observers resolve against the order's own store
        // even when the caller's X-Store-Code names a different one.
        $result = $isAdminOrder
            ? $placeOrder()
            : $this->cartService->inQuoteStoreScope($quote, $placeOrder);

        return $this->orderProvider->mapToDto($result['order'], $result['accessToken']);
    }

    /**
     * Cancel order
     */
    private function cancelOrder(array $context, array $uriVariables = []): Order
    {
        $reason = RequestInput::of($context['args']['input'] ?? [])->string('reason');

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->cancel($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Put an order on hold (admin / orders-write only).
     */
    private function holdOrder(array $context, array $uriVariables = []): Order
    {
        $reason = RequestInput::of($context['args']['input'] ?? [])->string('reason');

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->hold($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Release an order from hold (admin / orders-write only).
     */
    private function unholdOrder(array $context, array $uriVariables = []): Order
    {
        $reason = RequestInput::of($context['args']['input'] ?? [])->string('reason');

        $order = $this->resolveManagedOrder($context, $uriVariables);
        $order = $this->orderService->unhold($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Add a status-history comment to an order (admin / orders-write only).
     */
    private function addOrderComment(array $context, array $uriVariables = []): Order
    {
        $input = RequestInput::of($context['args']['input'] ?? []);
        $status = trim($input->string('status') ?? '');

        $order = $this->orderService->addNote(
            $this->resolveManagedOrder($context, $uriVariables),
            $input->string('comment', 'note') ?? '',
            $input->bool('notifyCustomer') ?? false,
            $input->bool('visibleOnFront') ?? false,
            $status !== '' ? $status : null,
        );

        return $this->orderProvider->mapToDto($order);
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
