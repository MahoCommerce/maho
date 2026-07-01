<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Mage\Customer\Api\Address;

#[ApiResource(
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Modify'],
    mahoCustomerScoped: true,
    shortName: 'Cart',
    description: 'View cart, add/remove items, apply coupons, set shipping & payment',
    provider: CartProvider::class,
    processor: CartProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/carts/{id}',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/read')",
            description: 'Get a cart by numeric ID. CartProvider enforces per-customer ownership via verifyCartAccess(); guest masked-ID lookups go through /guest-carts/{id}.',
        ),
        new Post(
            uriTemplate: '/carts',
            name: 'create_authenticated_cart',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Create a new cart for the authenticated customer',
        ),
        new Post(
            uriTemplate: '/carts/{id}/items',
            name: 'add_cart_item',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Add item to cart by numeric ID',
        ),
        new Put(
            uriTemplate: '/carts/{id}/items/{itemId}',
            name: 'update_cart_item',
            output: false,
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Update item quantity in cart',
        ),
        new Delete(
            uriTemplate: '/carts/{id}/items/{itemId}',
            name: 'remove_cart_item',
            output: false,
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Remove item from cart',
        ),
        // Authenticated-cart checkout sub-resources. These mirror the guest-cart
        // endpoints onto the numeric /carts/{id} path so a logged-in customer can
        // run the full checkout flow over REST (not only GraphQL). CartProvider /
        // CartProcessor resolve the cart generically via resolveCartFromRequest()
        // and verifyCartAccess() enforces per-customer ownership.
        new Post(
            uriTemplate: '/carts/{id}/coupon',
            name: 'apply_my_coupon',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Apply coupon to cart',
        ),
        new Delete(
            uriTemplate: '/carts/{id}/coupon',
            name: 'remove_my_coupon',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Remove coupon from cart',
        ),
        new Post(
            uriTemplate: '/carts/{id}/giftcards',
            name: 'apply_my_giftcard',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Apply gift card to cart',
        ),
        new Delete(
            uriTemplate: '/carts/{id}/giftcards/{code}',
            name: 'remove_my_giftcard',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Remove gift card from cart',
        ),
        new Get(
            uriTemplate: '/carts/{id}/totals',
            name: 'get_my_totals',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/read')",
            description: 'Get cart totals',
        ),
        new Post(
            uriTemplate: '/carts/{id}/shipping-methods',
            name: 'get_my_shipping',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Get available shipping methods for cart',
        ),
        new Get(
            uriTemplate: '/carts/{id}/payment-methods',
            name: 'get_my_payments',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/read')",
            description: 'Get available payment methods for cart',
        ),
        // Gift messages — cart-level and per-item, for both authenticated and
        // guest carts. PUT sets/updates (body: {sender, recipient, message});
        // DELETE clears. CartProcessor reuses CartService::setGiftMessage().
        new Put(
            uriTemplate: '/carts/{id}/gift-message',
            name: 'set_my_cart_gift_message',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Set the gift message on the cart',
        ),
        new Delete(
            uriTemplate: '/carts/{id}/gift-message',
            name: 'remove_my_cart_gift_message',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Remove the gift message from the cart',
        ),
        new Put(
            uriTemplate: '/carts/{id}/items/{itemId}/gift-message',
            name: 'set_my_item_gift_message',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Set the gift message on a cart item',
        ),
        new Delete(
            uriTemplate: '/carts/{id}/items/{itemId}/gift-message',
            name: 'remove_my_item_gift_message',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/write')",
            description: 'Remove the gift message from a cart item',
        ),
        new Put(
            uriTemplate: '/guest-carts/{id}/gift-message',
            name: 'set_guest_cart_gift_message',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Set the gift message on a guest cart',
        ),
        new Delete(
            uriTemplate: '/guest-carts/{id}/gift-message',
            name: 'remove_guest_cart_gift_message',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Remove the gift message from a guest cart',
        ),
        new Put(
            uriTemplate: '/guest-carts/{id}/items/{itemId}/gift-message',
            name: 'set_guest_item_gift_message',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Set the gift message on a guest cart item',
        ),
        new Delete(
            uriTemplate: '/guest-carts/{id}/items/{itemId}/gift-message',
            name: 'remove_guest_item_gift_message',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Remove the gift message from a guest cart item',
        ),
        new Post(
            uriTemplate: '/guest-carts',
            name: 'create_guest_cart',
            security: 'true',
            description: 'Create a new guest cart',
        ),
        new Get(
            uriTemplate: '/guest-carts/{id}',
            name: 'get_guest_cart',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Get a guest cart by masked ID',
        ),
        new Post(
            uriTemplate: '/guest-carts/{id}/items',
            name: 'add_guest_item',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Add item to guest cart',
        ),
        new Put(
            uriTemplate: '/guest-carts/{id}/items/{itemId}',
            name: 'update_guest_item',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            output: false,
            security: 'true',
            description: 'Update item quantity in guest cart',
        ),
        new Delete(
            uriTemplate: '/guest-carts/{id}/items/{itemId}',
            name: 'remove_guest_item',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            output: false,
            security: 'true',
            description: 'Remove item from guest cart',
        ),
        new Post(
            uriTemplate: '/guest-carts/{id}/coupon',
            name: 'apply_guest_coupon',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Apply coupon to guest cart',
        ),
        new Delete(
            uriTemplate: '/guest-carts/{id}/coupon',
            name: 'remove_guest_coupon',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Remove coupon from guest cart',
        ),
        new Post(
            uriTemplate: '/guest-carts/{id}/giftcards',
            name: 'apply_guest_giftcard',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Apply gift card to guest cart',
        ),
        new Delete(
            uriTemplate: '/guest-carts/{id}/giftcards/{code}',
            name: 'remove_guest_giftcard',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Remove gift card from guest cart',
        ),
        new Get(
            uriTemplate: '/guest-carts/{id}/totals',
            name: 'get_guest_totals',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Get guest cart totals',
        ),
        new Post(
            uriTemplate: '/guest-carts/{id}/shipping-methods',
            name: 'get_guest_shipping',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Get available shipping methods for guest cart',
        ),
        new Get(
            uriTemplate: '/guest-carts/{id}/payment-methods',
            name: 'get_guest_payments',
            uriVariables: ['id' => new Link(fromClass: Cart::class, identifiers: [])],
            security: 'true',
            description: 'Get available payment methods for guest cart',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: 'true',
            name: 'item_query',
            description: 'Get a cart by ID',
        ),
        new QueryCollection(
            security: 'true',
            name: 'collection_query',
            description: 'Get carts',
        ),
        new Query(
            security: 'true',
            name: 'getCartByMaskedId',
            args: ['maskedId' => ['type' => 'String!']],
            description: 'Get cart by masked ID',
            resolver: CustomQueryResolver::class,
        ),
        new Query(
            name: 'customerCart',
            args: [],
            description: 'Get current customer active cart',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('carts/read')",
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            security: 'true',
            name: 'createCart',
            args: ['storeId' => ['type' => 'Int', 'description' => 'Optional store ID, defaults to current store']],
            description: 'Create an empty cart',
        ),
        new Mutation(
            security: 'true',
            name: 'addToCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'sku' => ['type' => 'String!'],
                'qty' => ['type' => 'Float!'],
                'options' => ['type' => 'Iterable', 'description' => 'Custom options as {optionId: valueId} pairs'],
                'links' => ['type' => '[Int]', 'description' => 'Downloadable link IDs to purchase'],
                'superGroup' => ['type' => 'Iterable', 'description' => 'Grouped product qty map: {childProductId: qty}'],
                'bundleOption' => ['type' => 'Iterable', 'description' => 'Bundle option selections: {optionId: selectionId}'],
                'bundleOptionQty' => ['type' => 'Iterable', 'description' => 'Bundle option quantities: {optionId: qty}'],
            ],
            description: 'Add item to cart',
        ),
        new Mutation(
            security: 'true',
            name: 'updateCartItemQty',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'itemId' => ['type' => 'ID!'], 'qty' => ['type' => 'Float!']],
            description: 'Update cart item quantity',
        ),
        new Mutation(
            security: 'true',
            name: 'removeCartItem',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'itemId' => ['type' => 'ID!']],
            description: 'Remove item from cart',
        ),
        new Mutation(
            security: 'true',
            name: 'applyCouponToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'couponCode' => ['type' => 'String!']],
            description: 'Apply coupon code to cart',
        ),
        new Mutation(
            security: 'true',
            name: 'removeCouponFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String']],
            description: 'Remove coupon code from cart',
        ),
        new Mutation(
            security: 'true',
            name: 'setShippingAddressOnCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'firstName' => ['type' => 'String!'],
                'lastName' => ['type' => 'String!'],
                'street' => ['type' => '[String!]!'],
                'city' => ['type' => 'String!'],
                'region' => ['type' => 'String'],
                'regionId' => ['type' => 'Int'],
                'postcode' => ['type' => 'String!'],
                'countryId' => ['type' => 'String!'],
                'telephone' => ['type' => 'String!'],
                'company' => ['type' => 'String'],
            ],
            description: 'Set shipping address on cart',
        ),
        new Mutation(
            security: 'true',
            name: 'setBillingAddressOnCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'firstName' => ['type' => 'String!'],
                'lastName' => ['type' => 'String!'],
                'street' => ['type' => '[String!]!'],
                'city' => ['type' => 'String!'],
                'region' => ['type' => 'String'],
                'regionId' => ['type' => 'Int'],
                'postcode' => ['type' => 'String!'],
                'countryId' => ['type' => 'String!'],
                'telephone' => ['type' => 'String!'],
                'company' => ['type' => 'String'],
                'sameAsShipping' => ['type' => 'Boolean', 'description' => 'Copy from shipping address'],
            ],
            description: 'Set billing address on cart',
        ),
        new Mutation(
            security: 'true',
            name: 'setShippingMethodOnCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'carrierCode' => ['type' => 'String!'],
                'methodCode' => ['type' => 'String!'],
            ],
            description: 'Set shipping method on cart',
        ),
        new Mutation(
            security: 'true',
            name: 'setPaymentMethodOnCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'methodCode' => ['type' => 'String!'],
            ],
            description: 'Set payment method on cart',
        ),
        new Mutation(
            name: 'assignCustomerToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'customerId' => ['type' => 'ID!']],
            description: 'Assign customer to cart',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('carts/write')",
        ),
        new Mutation(
            security: 'true',
            name: 'applyGiftcardToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Apply gift card to cart',
        ),
        new Mutation(
            security: 'true',
            name: 'removeGiftcardFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Remove gift card from cart',
        ),
        new Mutation(
            security: 'true',
            // Named without a leading "Cart" so ApiPlatform's auto-appended
            // resource suffix reads as `setGiftMessageCart`, not a stuttering
            // `setCartGiftMessageCart`.
            name: 'setGiftMessage',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'itemId' => ['type' => 'ID', 'description' => 'Target a cart item; omit for the whole cart'],
                'sender' => ['type' => 'String!'],
                'recipient' => ['type' => 'String!'],
                'message' => ['type' => 'String!'],
            ],
            description: 'Set the gift message on the cart or a cart item',
        ),
        new Mutation(
            security: 'true',
            name: 'removeGiftMessage',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'itemId' => ['type' => 'ID', 'description' => 'Target a cart item; omit for the whole cart'],
            ],
            description: 'Remove the gift message from the cart or a cart item',
        ),
    ],
)]
class Cart extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Admin cart operations mirror the backend "Create Order" flow. */
    public const ADMIN_RESOURCE = 'sales/order/actions/create';

    #[ApiProperty(description: 'Cart/quote entity ID', writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Masked ID for guest cart access', writable: false)]
    public ?string $maskedId = null;

    #[ApiProperty(description: 'Associated customer ID, null for guest carts', writable: false)]
    public ?int $customerId = null;

    #[ApiProperty(description: 'Store ID', writable: false)]
    public int $storeId = 1;

    #[ApiProperty(description: 'Whether the cart is active (not yet ordered)', writable: false)]
    public bool $isActive = true;

    /**
     * Cart line items. Typed as untyped array so ApiPlatform's GraphQL exposes
     * this as Iterable scalar (queryable bare, returns JSON array of CartItem
     * shape) rather than wrapping in an IterableCursorConnection. The connection
     * wrapping requires CartItem to be a registered ApiResource for edge node
     * resolution, but CartItem is intentionally a plain DTO (no ApiResource
     * attribute) - so the connection resolver returns null edges. REST already
     * returns items as a flat array; mirror that in GraphQL.
     *
     * If a client needs to enumerate items: `cart { items }` returns the array,
     * client iterates and reads .sku, .qty, .priceInclTax, etc. as normal JSON.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ApiProperty(description: 'Cart line items', writable: false)]
    public array $items = [];

    /** @var array{subtotal: float, subtotalInclTax: float, subtotalWithDiscount: float, discountAmount: ?float, shippingAmount: ?float, shippingAmountInclTax: ?float, taxAmount: float, grandTotal: float, giftcardAmount: ?float}|array{} */
    #[ApiProperty(description: 'Cart price totals', writable: false)]
    public array $prices = [];

    #[ApiProperty(description: 'Billing address', writable: false)]
    public ?Address $billingAddress = null;

    #[ApiProperty(description: 'Shipping address', writable: false)]
    public ?Address $shippingAddress = null;

    /** @var array<array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}> */
    #[ApiProperty(description: 'Available shipping methods for current address', writable: false)]
    public array $availableShippingMethods = [];

    /** @var array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}|null */
    #[ApiProperty(description: 'Currently selected shipping method', writable: false)]
    public ?array $selectedShippingMethod = null;

    /** @var array<array{code: string, title: string}> */
    #[ApiProperty(description: 'Available payment methods', writable: false)]
    public array $availablePaymentMethods = [];

    /** @var array{code: string, title: string}|null */
    #[ApiProperty(description: 'Currently selected payment method', writable: false)]
    public ?array $selectedPaymentMethod = null;

    /** @var array{code: string, discountAmount: float}|null */
    #[ApiProperty(description: 'Applied coupon code and discount', writable: false)]
    public ?array $appliedCoupon = null;

    /** @var array<array{code: string, balance: float, appliedAmount: float}> */
    #[ApiProperty(description: 'Applied gift cards', writable: false)]
    public array $appliedGiftcards = [];

    /** @var array{sender: string, recipient: string, message: string}|null */
    #[ApiProperty(description: 'Cart-level gift message (set via /carts/{id}/gift-message)', writable: false)]
    public ?array $giftMessage = null;

    #[ApiProperty(description: 'Cart currency code', writable: false)]
    public string $currency = 'USD';

    #[ApiProperty(description: 'Number of distinct items', writable: false)]
    public int $itemsCount = 0;

    #[ApiProperty(description: 'Total quantity across all items', writable: false)]
    public float $itemsQty = 0;

    #[ApiProperty(description: 'Creation date (UTC)', writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(description: 'Last update date (UTC)', writable: false)]
    public ?string $updatedAt = null;

    public function __construct() {}

}
