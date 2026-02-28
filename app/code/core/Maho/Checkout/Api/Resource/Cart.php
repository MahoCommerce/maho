<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Checkout\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\Checkout\Api\State\Provider\CartProvider;
use Maho\Checkout\Api\State\Processor\CartProcessor;
use Maho\Customer\Api\Resource\Address;

#[ApiResource(
    shortName: 'Cart',
    description: 'Shopping cart resource',
    provider: CartProvider::class,
    processor: CartProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/carts/{id}',
            description: 'Get a cart by ID',
        ),
        new Post(
            uriTemplate: '/carts',
            description: 'Create a new cart',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a cart by ID',
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get carts',
        ),
        new Query(
            name: 'getCartByMaskedId',
            args: ['maskedId' => ['type' => 'String!']],
            description: 'Get cart by masked ID',
            resolver: CustomQueryResolver::class,
        ),
        new Query(
            name: 'customerCart',
            args: [],
            description: 'Get current customer active cart',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            name: 'createCart',
            args: ['storeId' => ['type' => 'Int', 'description' => 'Optional store ID, defaults to current store']],
            description: 'Create an empty cart',
        ),
        new Mutation(
            name: 'addToCart',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'sku' => ['type' => 'String!'],
                'qty' => ['type' => 'Float!'],
                'fulfillmentType' => ['type' => 'String', 'description' => 'SHIP (default) or PICKUP'],
                'options' => ['type' => 'Iterable', 'description' => 'Custom options as {optionId: valueId} pairs'],
                'links' => ['type' => '[Int]', 'description' => 'Downloadable link IDs to purchase'],
                'superGroup' => ['type' => 'Iterable', 'description' => 'Grouped product qty map: {childProductId: qty}'],
                'bundleOption' => ['type' => 'Iterable', 'description' => 'Bundle option selections: {optionId: selectionId}'],
                'bundleOptionQty' => ['type' => 'Iterable', 'description' => 'Bundle option quantities: {optionId: qty}'],
            ],
            description: 'Add item to cart',
        ),
        new Mutation(
            name: 'updateCartItemQty',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'itemId' => ['type' => 'ID!'], 'qty' => ['type' => 'Float!']],
            description: 'Update cart item quantity',
        ),
        new Mutation(
            name: 'setCartItemFulfillment',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'itemId' => ['type' => 'ID!'],
                'fulfillmentType' => ['type' => 'String!', 'description' => 'SHIP or PICKUP'],
            ],
            description: 'Set fulfillment type for a cart item (SHIP or PICKUP for BOPIS)',
        ),
        new Mutation(
            name: 'removeCartItem',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'itemId' => ['type' => 'ID!']],
            description: 'Remove item from cart',
        ),
        new Mutation(
            name: 'applyCouponToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'couponCode' => ['type' => 'String!']],
            description: 'Apply coupon code to cart',
        ),
        new Mutation(
            name: 'removeCouponFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String']],
            description: 'Remove coupon code from cart',
        ),
        new Mutation(
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
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'applyGiftcardToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Apply gift card to cart',
        ),
        new Mutation(
            name: 'removeGiftcardFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Remove gift card from cart',
        ),
    ],
)]
class Cart
{
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

    /** @var CartItem[] */
    #[ApiProperty(description: 'Cart line items', writable: false)]
    public array $items = [];

    /** @var array{subtotal: float, subtotalInclTax: float, subtotalWithDiscount: float, discountAmount: ?float, shippingAmount: ?float, shippingAmountInclTax: ?float, taxAmount: float, grandTotal: float, giftcardAmount: ?float} */
    #[ApiProperty(description: 'Cart price totals', writable: false)]
    /** @phpstan-ignore property.defaultValue */
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

    #[ApiProperty(description: 'Cart currency code', writable: false)]
    public string $currency = 'AUD';

    #[ApiProperty(description: 'Number of distinct items', writable: false)]
    public int $itemsCount = 0;

    #[ApiProperty(description: 'Total quantity across all items', writable: false)]
    public float $itemsQty = 0;

    #[ApiProperty(description: 'Creation date (UTC)', writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(description: 'Last update date (UTC)', writable: false)]
    public ?string $updatedAt = null;

    public function __construct() {}

    /**
     * Module-provided extension data.
     * Populated via api_{resource}_dto_build event. Modules can append
     * arbitrary keyed data here without modifying core API resources.
     * @var array<string, mixed>
     */
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];

}
