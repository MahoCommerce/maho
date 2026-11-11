<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Customer\Api\CustomAttributes;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Cart State Processor - Handles cart mutations for API Platform.
 */
final class CartProcessor extends \Maho\ApiPlatform\Processor
{
    private CartMapper $cartMapper;
    private \Mage_Checkout_Service_Cart $cartService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartMapper = new CartMapper();
        $this->cartService = \Mage::getService('checkout/cart');
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
            'setShippingAddressOn', 'set_my_shipping_address', 'set_guest_shipping_address' => $this->setShippingAddressOnCart($context, $uriVariables),
            'setBillingAddressOn', 'set_my_billing_address', 'set_guest_billing_address' => $this->setBillingAddressOnCart($context, $uriVariables),
            'get_guest_shipping' => $this->getShippingMethodsForCart($context, $uriVariables, focused: true),
            'get_my_shipping' => $this->getShippingMethodsForCart($context, $uriVariables, focused: false),
            'setShippingMethodOn', 'set_my_shipping_method', 'set_guest_shipping_method' => $this->setShippingMethodOnCart($context, $uriVariables),
            'setPaymentMethodOn', 'set_my_payment_method', 'set_guest_payment_method' => $this->setPaymentMethodOnCart($context, $uriVariables),
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
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked]
            = CartRequest::resolve($uriVariables, $context);

        return $this->cartService->requireAccessible(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->isPrivilegedCartActor(),
        );
    }

    /**
     * Whether the caller may bypass cart ownership and act on any cart.
     *
     * Admins are gated upstream by AdminAclListener (Cart::ADMIN_RESOURCE), so
     * they're trusted here. A service token is trusted only when it actually
     * holds the carts/write grant: a bare service-account token without it is
     * treated as an ordinary caller and can't reach arbitrary carts through the
     * enumerable numeric /carts/{id} path, even on the public guest operations
     * whose security expression admits everyone.
     */
    private function isPrivilegedCartActor(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return $this->isApiUser() && $this->requireUser()->hasPermission('carts/write');
    }

    private function actor(): \Mage_Checkout_Model_Actor
    {
        return $this->isPrivilegedCartActor() ? \Mage_Checkout_Model_Actor::Staff : \Mage_Checkout_Model_Actor::Customer;
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

        $quote = $this->cartService->create($customerId, CartRequest::storeId($storeId));

        return $this->cartMapper->mapQuoteToCart($quote, false);
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

        $quote = $this->cartService->create($customerId, CartRequest::storeId($storeId));

        return $this->cartMapper->mapQuoteToCart($quote, false);
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
        $this->assertRequestedStoreAllowed($storeId, $user instanceof ApiUser ? $user : null);

        return $storeId;
    }

    /**
     * Add item to cart
     */
    private function addItemToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $customPrice = $this->parseCustomPrice($args);

        $recreated = false;
        $quote = $this->resolveCartForItemAdd($context, $uriVariables, $recreated);
        $product = $this->cartService->getProductBySku(RequestInput::of($args)->string('sku') ?? '', (int) $quote->getStoreId());
        $this->cartService->addProduct($quote, $product, $this->buildBuyRequest($args), $this->actor(), $customPrice);

        $cart = $this->cartMapper->mapQuoteToCart($quote);
        $cart->cartRecreated = $recreated;
        return $cart;
    }

    /**
     * Turn the camelCase fields of an add to cart into a buy request. Without a qty, the cart service gives the
     * default qty of the product.
     */
    private function buildBuyRequest(array $args): \Maho\DataObject
    {
        $buyRequest = new \Maho\DataObject();
        $qty = RequestInput::of($args)->float('qty');
        if ($qty !== null) {
            $buyRequest->setQty($qty);
        }
        foreach ([
            'options' => 'options',
            'options_files' => 'options_files',
            'links' => 'links',
            'superGroup' => 'super_group',
            'bundleOption' => 'bundle_option',
            'bundleOptionQty' => 'bundle_option_qty',
            'giftcardAmount' => 'giftcard_amount',
            'giftcardSenderName' => 'giftcard_sender_name',
            'giftcardSenderEmail' => 'giftcard_sender_email',
            'giftcardRecipientName' => 'giftcard_recipient_name',
            'giftcardRecipientEmail' => 'giftcard_recipient_email',
            'giftcardMessage' => 'giftcard_message',
            'giftcardDeliveryDate' => 'giftcard_delivery_date',
        ] as $field => $key) {
            if (!empty($args[$field])) {
                $buyRequest->setData($key, $args[$field]);
            }
        }
        return $buyRequest;
    }

    /**
     * Read the optional customPrice override. The cart service decides who may set it.
     */
    private function parseCustomPrice(array $args): ?float
    {
        if (!isset($args['customPrice']) || $args['customPrice'] === '') {
            return null;
        }
        if (!is_numeric($args['customPrice'])) {
            throw new BadRequestHttpException('customPrice must be a number');
        }
        return (float) $args['customPrice'];
    }

    /**
     * Resolve the target cart for an add-to-cart. On the public guest path a
     * stale/expired/non-existent masked cart is transparently replaced with a
     * fresh guest cart (flagged via $recreated) so a returning shopper whose
     * quote was pruned can keep shopping instead of hitting a 404. An id that is
     * not a well-formed masked id still 404s, and so does every /carts/{id} add.
     */
    private function resolveCartForItemAdd(array $context, array $uriVariables, bool &$recreated): \Mage_Sales_Model_Quote
    {
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked, 'maskedId' => $maskedId]
            = CartRequest::resolve($uriVariables, $context);

        // A malformed id, such as the numeric quote id the create response also
        // returns, is a caller mistake, not a pruned cart. The gate reads the id
        // the lookup used, never a second, possibly different, id of its own.
        if (!$quote && $maskedId !== null && $this->isGuestCartRequest($context)) {
            $recreated = true;
            return $this->cartService->create(null, CartRequest::storeId());
        }

        return $this->cartService->requireAccessible(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->isPrivilegedCartActor(),
        );
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
        $input = RequestInput::of($args);
        $customPrice = $this->parseCustomPrice($args);

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->updateItem(
            $quote,
            $input->int('itemId') ?? (int) ($uriVariables['itemId'] ?? 0),
            $input->float('qty'),
            $customPrice,
            $this->actor(),
        );

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Remove item from cart
     */
    private function removeItemFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeItem($quote, RequestInput::of($args)->int('itemId') ?? (int) ($uriVariables['itemId'] ?? 0));

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Apply coupon code to cart
     */
    private function applyCouponToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        // Accept both field names: GraphQL/authenticated callers send couponCode,
        // the guest REST body uses code.
        $couponCode = RequestInput::of($args)->string('couponCode', 'code') ?? '';

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->applyCoupon($quote, $couponCode, $this->actor());

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Remove coupon code from cart
     */
    private function removeCouponFromCart(array $context, array $uriVariables): Cart
    {
        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeCoupon($quote);

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Set shipping address on cart
     */
    private function setShippingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $quote = $this->resolveAndVerify($context, $uriVariables);
        CustomAttributes::applyToCartAddress($quote->getShippingAddress(), $args['customAttributes'] ?? null);
        try {
            $quote = $this->cartService->setShippingAddress($quote, CartRequest::mapAddress($args));
        } catch (\Mage_Core_Exception_Input $e) {
            throw CartRequest::withAddressFieldNames($e);
        }

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Set billing address on cart
     */
    private function setBillingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        // FILTER_VALIDATE_BOOLEAN also normalizes stringly-typed clients
        // ("true"/"false"/"1"/"0"), which strict_types would otherwise 500 on
        $sameAsShipping = filter_var($args['sameAsShipping'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $addressData = CartRequest::mapAddress($args);
        if ($sameAsShipping) {
            foreach (array_keys(\Maho\ApiPlatform\Service\CustomAttributes::userDefined('customer_address')) as $code) {
                $quote->getBillingAddress()->setData($code, $quote->getShippingAddress()->getData($code));
            }
        } else {
            CustomAttributes::applyToCartAddress($quote->getBillingAddress(), $args['customAttributes'] ?? null);
        }
        try {
            $quote = $this->cartService->setBillingAddress($quote, $addressData, $sameAsShipping);
        } catch (\Mage_Core_Exception_Input $e) {
            throw CartRequest::withAddressFieldNames($e);
        }

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Get available shipping methods for the cart. With an optional {address: {...}} body, the cart estimates the
     * shipping for the country, the region, the postcode and the city of the address first, as the cart page does.
     * Returns the cart (the mapper populates availableShippingMethods).
     */
    private function getShippingMethodsForCart(array $context, array $uriVariables, bool $focused): Cart|Response
    {
        $args = $context['args']['input'] ?? [];
        $address = $args['address'] ?? null;

        $quote = $this->resolveAndVerify($context, $uriVariables);

        if (is_array($address) && $address !== []) {
            $estimate = \Mage::getModel('sales/quote_address')->addData(CartRequest::mapAddress($address));
            $quote = $this->cartService->estimateShipping($quote, $estimate);
        } else {
            // No address in the body: the saved rates can be older than the last change of the cart
            $this->cartService->refreshShippingRates($quote);
        }

        // Guest frontend contract: return the plain list of available shipping
        // methods (code/title/price). The authenticated /carts/{id} variant
        // returns the full Cart (availableShippingMethods included).
        if ($focused) {
            return $this->respondRaw($this->cartMapper->getShippingMethodsOfCart($quote));
        }

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Set shipping method on cart
     */
    private function setShippingMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $carrierCode = is_scalar($args['carrierCode'] ?? null) ? (string) $args['carrierCode'] : '';
        $methodCode = is_scalar($args['methodCode'] ?? null) ? (string) $args['methodCode'] : '';

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setShippingMethod($quote, $carrierCode, $methodCode);

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Set payment method on cart
     */
    private function setPaymentMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $methodCode = is_scalar($args['methodCode'] ?? null) ? (string) $args['methodCode'] : '';
        $additionalData = $args['additionalData'] ?? null;

        if ($additionalData !== null && !is_array($additionalData)) {
            throw new BadRequestHttpException('additionalData must be an object');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setPaymentMethod($quote, $methodCode, $additionalData, CartRequest::backOfficeActor($this->isPrivilegedCartActor()));

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Assign customer to cart (merge guest cart)
     */
    private function assignCustomerToCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $input = RequestInput::of($args);
        $cartId = $input->int('cartId');
        $maskedId = $input->string('maskedId');
        $requestedCustomerId = $input->int('customerId');

        // Staff can assign any customer to any cart
        if ($this->isPrivilegedCartActor()) {
            $quote = $this->cartService->requireAccessible(
                CartRequest::load($cartId ?: null, $maskedId),
                $maskedId !== null,
                null,
                true,
            );
            $customer = \Mage::getModel('customer/customer');
            if ($requestedCustomerId) {
                $customer->load($requestedCustomerId);
            }
            $quote = $this->cartService->assignCustomer($quote, $customer);

            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // Customer self-assignment (merge guest cart)
        $customerId = $this->requireCustomerId();
        if ($requestedCustomerId && $requestedCustomerId !== $customerId) {
            throw new \Mage_Core_Exception_Forbidden('Cannot assign a different customer to this cart');
        }

        $quote = $this->cartService->merge($maskedId ?? '', $customerId, (int) \Mage::app()->getStore()->getId());

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * @throws \Mage_Core_Exception_NoSuchEntity when the store has no gift card module
     */
    private function giftcardService(): \Maho_Giftcard_Service_Giftcard
    {
        if (!\Mage::helper('core')->isModuleEnabled('Maho_Giftcard')) {
            throw new \Mage_Core_Exception_NoSuchEntity(\Mage::helper('checkout')->__('Gift cards are not available'));
        }

        return \Mage::getService('giftcard/giftcard');
    }

    /**
     * Apply gift card to cart
     */
    private function applyGiftcardToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = RequestInput::of($args)->string('giftcardCode') ?? '';

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->giftcardService()->applyToCart($quote, $giftcardCode, null, $this->actor());

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Remove gift card from cart
     */
    private function removeGiftcardFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = trim(RequestInput::of($args)->string('giftcardCode') ?? '');

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->giftcardService()->removeFromCart($quote, $giftcardCode);

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Set the gift message on the cart, or on a single item when an itemId is
     * present (URI sub-resource or `itemId` arg).
     */
    private function setGiftMessage(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $input = RequestInput::of($args);
        $itemId = $this->resolveGiftMessageItemId($args, $uriVariables);
        $sender = $input->string('sender') ?? '';
        $recipient = $input->string('recipient') ?? '';
        $message = $input->string('message') ?? '';

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setGiftMessage($quote, $itemId, $sender, $recipient, $message);

        return $this->cartMapper->mapQuoteToCart($quote);
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

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Resolve the optional target item id for a gift-message operation. Present
     * for item-level endpoints (URI {itemId} or GraphQL itemId arg), null for
     * cart-level. process() already bridges the {itemId} route param into args.
     */
    private function resolveGiftMessageItemId(array $args, array $uriVariables): ?int
    {
        $itemId = ($args['itemId'] ?? '') === '' ? $uriVariables['itemId'] ?? null : RequestInput::of($args)->int('itemId');
        return $itemId !== null && $itemId !== '' ? (int) $itemId : null;
    }

}
