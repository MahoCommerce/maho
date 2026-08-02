<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cart State Processor - Handles cart mutations for API Platform.
 */
final class CartProcessor extends \Maho\ApiPlatform\Processor
{
    private CartMapper $cartMapper;
    private CartService $cartService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartMapper = new CartMapper();
        $this->cartService = new CartService();
    }

    /**
     * Process cart mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Cart|Response
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Bridge REST request body into context args (GraphQL populates args natively)
        $this->normalizeGraphQlInput($context);

        // Map uriVariables for sub-resource params. itemId is
        // declared in URI templates but not in the operation's
        // uriVariables map, so API Platform doesn't include it in
        // the resolved $uriVariables array. Pull from Request
        // route params as a fallback so PUT/DELETE on
        // /items/{itemId} actually receive the id.
        $req = $context['request'] ?? null;
        if (!isset($uriVariables['itemId']) && $req instanceof \Symfony\Component\HttpFoundation\Request) {
            $rp = $req->attributes->get('_route_params') ?? [];
            if (isset($rp['itemId'])) {
                $uriVariables['itemId'] = $rp['itemId'];
            }
        }
        // Same fallback for the gift card {code} on DELETE /guest-carts/{id}/giftcards/{code}:
        // it isn't in the operation's uriVariables map, so recover it from the route params.
        if (!isset($uriVariables['code']) && $req instanceof \Symfony\Component\HttpFoundation\Request) {
            $rp = $req->attributes->get('_route_params') ?? [];
            if (isset($rp['code'])) {
                $uriVariables['code'] = $rp['code'];
            }
        }
        // Map uriVariables for sub-resource params
        if (isset($uriVariables['itemId']) && !isset($context['args']['input']['itemId'])) {
            $context['args']['input']['itemId'] = $uriVariables['itemId'];
        }
        if (isset($uriVariables['code']) && !isset($context['args']['input']['giftcardCode'])) {
            $context['args']['input']['giftcardCode'] = (string) $uriVariables['code'];
        }

        return match ($operationName) {
            'create', 'create_guest_cart' => $this->createEmptyCart($context),
            'create_authenticated_cart' => $this->createAuthenticatedCart($context),
            'addTo', 'add_guest_item', 'add_cart_item' => $this->addItemToCart($context, $uriVariables),
            'updateItemQtyIn', 'update_guest_item', 'update_cart_item' => $this->updateCartItem($context, $uriVariables),
            'removeItemFrom', 'remove_guest_item', 'remove_cart_item' => $this->removeItemFromCart($context, $uriVariables),
            'applyCouponTo', 'apply_guest_coupon', 'apply_my_coupon' => $this->applyCouponToCart($context, $uriVariables),
            'removeCouponFrom', 'remove_guest_coupon', 'remove_my_coupon' => $this->removeCouponFromCart($context, $uriVariables),
            'setShippingAddressOn' => $this->setShippingAddressOnCart($context, $uriVariables),
            'setBillingAddressOn' => $this->setBillingAddressOnCart($context, $uriVariables),
            'get_guest_shipping' => $this->getShippingMethodsForCart($context, $uriVariables, focused: true),
            'get_my_shipping' => $this->getShippingMethodsForCart($context, $uriVariables, focused: false),
            'setShippingMethodOn' => $this->setShippingMethodOnCart($context, $uriVariables),
            'setPaymentMethodOn' => $this->setPaymentMethodOnCart($context, $uriVariables),
            'assignCustomerTo' => $this->assignCustomerToCart($context),
            'applyGiftcardTo', 'apply_guest_giftcard', 'apply_my_giftcard' => $this->applyGiftcardToCart($context, $uriVariables),
            'removeGiftcardFrom', 'remove_guest_giftcard', 'remove_my_giftcard' => $this->removeGiftcardFromCart($context, $uriVariables),
            'setGiftMessageOn', 'set_my_cart_gift_message', 'set_my_item_gift_message',
            'set_guest_cart_gift_message', 'set_guest_item_gift_message' => $this->setGiftMessage($context, $uriVariables),
            'removeGiftMessageFrom', 'remove_my_cart_gift_message', 'remove_my_item_gift_message',
            'remove_guest_cart_gift_message', 'remove_guest_item_gift_message' => $this->removeGiftMessage($context, $uriVariables),
            default => $data instanceof Cart ? $data : new Cart(),
        };
    }

    /**
     * Resolve cart and verify access, shared by all operation methods
     */
    private function resolveAndVerify(array $context, array $uriVariables): \Mage_Sales_Model_Quote
    {
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked] =
            $this->cartService->resolveCartFromRequest($uriVariables, $context);

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        $this->cartService->verifyCartAccess(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->isPrivilegedCartActor(),
        );

        return $quote;
    }

    /**
     * Whether the caller may bypass cart ownership and act on any cart.
     *
     * Admins are gated upstream by AdminAclListener (Cart::ADMIN_RESOURCE), so
     * they're trusted here. A service token is trusted only when it actually
     * holds the carts/write grant: a bare service-account token without it is
     * treated as an ordinary caller and can't reach arbitrary carts through the
     * enumerable numeric /carts/{id} path. This closes the gap left by the
     * overridden process() bypassing the base Processor's requirePermission().
     */
    private function isPrivilegedCartActor(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return $this->isApiUser() && $this->getAuthorizedUser()->hasPermission('carts/write');
    }

    /**
     * Create an empty cart (GraphQL mutation / guest-cart REST endpoint).
     * customerId is whatever the GraphQL caller passed in context; null
     * yields a guest quote with a masked ID.
     */
    private function createEmptyCart(array $context): Cart
    {
        $customerId = $context['customer_id'] ?? null;
        $storeId = $this->resolveRequestedStoreId($context);

        $result = $this->cartService->createEmptyCart($customerId, $storeId);

        return $this->cartMapper->mapQuoteToCart($result['quote'], false);
    }

    /**
     * Create a cart for the authenticated REST caller.
     *
     * The /carts POST operation can only be reached when the firewall has
     * already established a customer (ROLE_CUSTOMER), admin (ROLE_ADMIN), or service-account token
     * (see security expression on the Cart resource). We resolve the customer
     * id from the auth context rather than from the request body so a
     * customer can't try to provision a cart against someone else's account.
     */
    private function createAuthenticatedCart(array $context): Cart
    {
        $customerId = $this->getAuthenticatedCustomerId();
        $storeId = $this->resolveRequestedStoreId($context);

        $result = $this->cartService->createEmptyCart($customerId, $storeId);

        return $this->cartMapper->mapQuoteToCart($result['quote'], false);
    }

    /**
     * Resolve a body-level storeId and enforce the token's store allowlist on
     * it. The request-level listeners only inspect ?store= / X-Store-Code, so a
     * storeId in the payload would otherwise bypass the allowlist check. Guests
     * carry no ApiUser and pass through.
     */
    private function resolveRequestedStoreId(array $context): ?int
    {
        $storeId = $context['args']['input']['storeId'] ?? null;
        if ($storeId === null) {
            return null;
        }

        $storeId = (int) $storeId;
        $user = $this->security?->getUser();
        if ($user instanceof ApiUser && !$user->canAccessStore($storeId)) {
            throw new AccessDeniedHttpException("Token is not authorized for store: {$storeId}");
        }

        return $storeId;
    }

    /**
     * Add item to cart
     */
    private function addItemToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $sku = $args['sku'] ?? '';
        $qty = (float) ($args['qty'] ?? 1);

        // Build buy request options
        $buyOptions = [];
        if (!empty($args['options'])) {
            $buyOptions['options'] = $args['options'];
        }
        // File-type custom options: the base64 uploads must be forwarded to
        // CartService::addItem, which injects them into the buy request. Without
        // this they're dropped and a provided file reads as a missing required
        // option (add-to-cart 400s).
        if (!empty($args['options_files'])) {
            $buyOptions['options_files'] = $args['options_files'];
        }
        if (!empty($args['links'])) {
            $buyOptions['links'] = $args['links'];
        }
        if (!empty($args['superGroup'])) {
            $buyOptions['super_group'] = $args['superGroup'];
        }
        if (!empty($args['bundleOption'])) {
            $buyOptions['bundle_option'] = $args['bundleOption'];
        }
        if (!empty($args['bundleOptionQty'])) {
            $buyOptions['bundle_option_qty'] = $args['bundleOptionQty'];
        }
        foreach ([
            'giftcardAmount' => 'giftcard_amount',
            'giftcardSenderName' => 'giftcard_sender_name',
            'giftcardSenderEmail' => 'giftcard_sender_email',
            'giftcardRecipientName' => 'giftcard_recipient_name',
            'giftcardRecipientEmail' => 'giftcard_recipient_email',
            'giftcardMessage' => 'giftcard_message',
            'giftcardDeliveryDate' => 'giftcard_delivery_date',
        ] as $camel => $snake) {
            if (!empty($args[$camel])) {
                $buyOptions[$snake] = $args[$camel];
            }
        }

        $recreated = false;
        $quote = $this->resolveCartForItemAdd($context, $uriVariables, $recreated);
        $quote = $this->cartService->addItem($quote, $sku, $qty, $buyOptions);

        $cart = $this->cartMapper->mapQuoteToCart($quote, false);
        $cart->cartRecreated = $recreated;
        return $cart;
    }

    /**
     * Resolve the target cart for an add-to-cart. On the public guest path a
     * stale/expired/non-existent masked cart is transparently replaced with a
     * fresh guest cart (flagged via $recreated) so a returning shopper whose
     * quote was pruned can keep shopping instead of hitting a 404. Authenticated
     * and numeric /carts/{id} adds still 404 on a missing cart.
     */
    private function resolveCartForItemAdd(array $context, array $uriVariables, bool &$recreated): \Mage_Sales_Model_Quote
    {
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked] =
            $this->cartService->resolveCartFromRequest($uriVariables, $context);

        if ($quote) {
            $this->cartService->verifyCartAccess(
                $quote,
                $byMasked,
                $this->getAuthenticatedCustomerId(),
                $this->isPrivilegedCartActor(),
            );
            return $quote;
        }

        if ($this->isGuestCartRequest($context)) {
            $recreated = true;
            return $this->cartService->createEmptyCart()['quote'];
        }

        throw new NotFoundHttpException('Cart not found');
    }

    /** True when the request targets the public /guest-carts/… path. */
    private function isGuestCartRequest(array $context): bool
    {
        $request = $context['request'] ?? null;
        return $request instanceof \Symfony\Component\HttpFoundation\Request
            && str_contains($request->getPathInfo(), '/guest-carts/');
    }

    /**
     * Update cart item quantity
     */
    private function updateCartItem(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $args['itemId'] ?? $uriVariables['itemId'] ?? null;
        $qty = (float) ($args['qty'] ?? 1);

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->updateItem($quote, (int) $itemId, $qty);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove item from cart
     */
    private function removeItemFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $args['itemId'] ?? $uriVariables['itemId'] ?? null;

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeItem($quote, (int) $itemId);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }


    /**
     * Apply coupon code to cart
     */
    private function applyCouponToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        // Accept both field names: GraphQL/authenticated callers send couponCode,
        // the guest REST body uses code.
        $couponCode = $args['couponCode'] ?? $args['code'] ?? '';

        if (!$couponCode) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Coupon code is required');
        }

        // Throttle anonymous/customer callers by IP: applying a coupon to a cart
        // is otherwise an unauthenticated oracle for enumerating auto-generated
        // coupon batches, the same risk the /coupons/validate endpoint guards.
        // POS/API callers are exempt (legitimate high-volume checkout).
        if (!$this->isAdmin() && !$this->isApiUser()) {
            $this->checkRateLimitByIp('cart_coupon', 'coupon_validate', 60);
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->applyCoupon($quote, $couponCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove coupon code from cart
     */
    private function removeCouponFromCart(array $context, array $uriVariables): Cart
    {
        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeCoupon($quote);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set shipping address on cart
     */
    private function setShippingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setShippingAddress($quote, $this->cartService->mapAddressInput($args));

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set billing address on cart
     */
    private function setBillingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $sameAsShipping = $args['sameAsShipping'] ?? false;

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $addressData = $sameAsShipping ? [] : $this->cartService->mapAddressInput($args);
        $quote = $this->cartService->setBillingAddress($quote, $addressData, $sameAsShipping);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Get available shipping methods for the cart. Accepts an optional
     * {address: {...}} body, when present, the address is applied to the
     * cart first so the rate calculator has something to evaluate. Returns
     * the cart (the mapper populates availableShippingMethods).
     */
    private function getShippingMethodsForCart(array $context, array $uriVariables, bool $focused): Cart|Response
    {
        $args = $context['args']['input'] ?? [];
        $address = $args['address'] ?? null;

        $quote = $this->resolveAndVerify($context, $uriVariables);

        if (is_array($address) && !empty($address)) {
            $quote = $this->cartService->setShippingAddress($quote, $this->cartService->mapAddressInput($address));
        }

        // Guest storefront contract: return the plain list of available shipping
        // methods (code/title/price). The authenticated /carts/{id} variant
        // returns the full Cart (availableShippingMethods included).
        if ($focused) {
            $shippingAddress = $quote->getShippingAddress();
            $methods = $shippingAddress && $shippingAddress->getId()
                ? $this->cartMapper->getAvailableShippingMethods($shippingAddress)
                : [];
            return $this->respondRaw($methods);
        }

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set shipping method on cart
     */
    private function setShippingMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $carrierCode = $args['carrierCode'] ?? '';
        $methodCode = $args['methodCode'] ?? '';

        if (!$carrierCode || !$methodCode) {
            throw new \RuntimeException('Carrier code and method code are required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setShippingMethod($quote, $carrierCode, $methodCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set payment method on cart
     */
    private function setPaymentMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $methodCode = $args['methodCode'] ?? '';
        $additionalData = $args['additionalData'] ?? null;

        if (!$methodCode) {
            throw new \RuntimeException('Payment method code is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setPaymentMethod($quote, $methodCode, $additionalData);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Assign customer to cart (merge guest cart)
     */
    private function assignCustomerToCart(array $context, array $uriVariables = []): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $requestedCustomerId = $args['customerId'] ?? null;

        // Admin/POS users can assign any customer to any cart
        if ($this->isPrivilegedCartActor()) {
            if (!$requestedCustomerId) {
                throw new \RuntimeException('Customer ID is required');
            }
            $quote = $this->cartService->getCart(
                $cartId ? (int) $cartId : null,
                $maskedId,
            );
            if (!$quote) {
                throw new \RuntimeException('Cart not found');
            }

            $customerId = (int) $requestedCustomerId;
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new \RuntimeException('Customer not found');
            }

            $quote->assignCustomer($customer);
            $quote->collectTotals()->save();

            return $this->cartMapper->mapQuoteToCart($quote, false);
        }

        // Customer self-assignment (merge guest cart)
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        if (!$maskedId) {
            throw new \RuntimeException('Masked cart ID is required');
        }
        if (!$authenticatedCustomerId) {
            throw new \RuntimeException('Authentication required');
        }

        $customerId = (int) $authenticatedCustomerId;
        if ($requestedCustomerId && (int) $requestedCustomerId !== $customerId) {
            throw new \RuntimeException('Cannot assign a different customer to this cart');
        }

        $quote = $this->cartService->mergeCarts($maskedId, $customerId);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Apply gift card to cart
     */
    private function applyGiftcardToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        // Throttle anonymous/customer callers by IP: applying a gift card to a
        // cart is otherwise an oracle for enumerating gift card codes, which
        // carry monetary value and whose apply errors differ per code-state.
        // POS/API callers are exempt (legitimate high-volume checkout).
        if (!$this->isAdmin() && !$this->isApiUser()) {
            $this->checkRateLimitByIp('cart_giftcard', 'giftcard_balance', 60);
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->applyGiftcard($quote, $giftcardCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove gift card from cart
     */
    private function removeGiftcardFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeGiftcard($quote, $giftcardCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set the gift message on the cart, or on a single item when an itemId is
     * present (URI sub-resource or `itemId` arg).
     */
    private function setGiftMessage(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $this->resolveGiftMessageItemId($args, $uriVariables);
        $sender = trim((string) ($args['sender'] ?? ''));
        $recipient = trim((string) ($args['recipient'] ?? ''));
        $message = (string) ($args['message'] ?? '');

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setGiftMessage($quote, $itemId, $sender, $recipient, $message);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove the gift message from the cart, or from a single item.
     */
    private function removeGiftMessage(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $this->resolveGiftMessageItemId($args, $uriVariables);

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeGiftMessage($quote, $itemId);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Resolve the optional target item id for a gift-message operation. Present
     * for item-level endpoints (URI {itemId} or GraphQL itemId arg), null for
     * cart-level. process() already bridges the {itemId} route param into args.
     */
    private function resolveGiftMessageItemId(array $args, array $uriVariables): ?int
    {
        $itemId = $args['itemId'] ?? $uriVariables['itemId'] ?? null;
        return $itemId !== null && $itemId !== '' ? (int) $itemId : null;
    }

}
