<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\State\Provider\CartProvider;
use Maho\ApiPlatform\State\Processor\CartProcessor;

#[ApiResource(
    shortName: 'Cart',
    description: 'Shopping cart resource',
    provider: CartProvider::class,
    processor: CartProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/carts/{id}',
            description: 'Get a cart by ID'
        ),
        new Post(
            uriTemplate: '/carts',
            description: 'Create a new cart'
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'cart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String']],
            description: 'Get cart by ID or masked ID'
        ),
        new Query(
            name: 'customerCart',
            args: [],
            description: 'Get current customer active cart'
        ),
        new Mutation(
            name: 'createCart',
            description: 'Create an empty cart'
        ),
        new Mutation(
            name: 'addToCart',
            args: [
                'cartId' => ['type' => 'ID!'],
                'sku' => ['type' => 'String!'],
                'qty' => ['type' => 'Float!'],
                'fulfillmentType' => ['type' => 'String', 'description' => 'SHIP (default) or PICKUP']
            ],
            description: 'Add item to cart'
        ),
        new Mutation(
            name: 'updateCartItemQty',
            args: ['cartId' => ['type' => 'ID!'], 'itemId' => ['type' => 'ID!'], 'qty' => ['type' => 'Float!']],
            description: 'Update cart item quantity'
        ),
        new Mutation(
            name: 'setCartItemFulfillment',
            args: [
                'cartId' => ['type' => 'ID!'],
                'itemId' => ['type' => 'ID!'],
                'fulfillmentType' => ['type' => 'String!', 'description' => 'SHIP or PICKUP']
            ],
            description: 'Set fulfillment type for a cart item (SHIP or PICKUP for BOPIS)'
        ),
        new Mutation(
            name: 'removeCartItem',
            args: ['cartId' => ['type' => 'ID!'], 'itemId' => ['type' => 'ID!']],
            description: 'Remove item from cart'
        ),
        new Mutation(
            name: 'applyCouponToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'couponCode' => ['type' => 'String!']],
            description: 'Apply coupon code to cart'
        ),
        new Mutation(
            name: 'removeCouponFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String']],
            description: 'Remove coupon code from cart'
        ),
        new Mutation(
            name: 'setShippingAddressOnCart',
            description: 'Set shipping address on cart'
        ),
        new Mutation(
            name: 'setBillingAddressOnCart',
            description: 'Set billing address on cart'
        ),
        new Mutation(
            name: 'setShippingMethodOnCart',
            description: 'Set shipping method on cart'
        ),
        new Mutation(
            name: 'setPaymentMethodOnCart',
            description: 'Set payment method on cart'
        ),
        new Mutation(
            name: 'assignCustomerToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'customerId' => ['type' => 'ID!']],
            description: 'Assign customer to cart'
        ),
        new Mutation(
            name: 'applyGiftcardToCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Apply gift card to cart'
        ),
        new Mutation(
            name: 'removeGiftcardFromCart',
            args: ['cartId' => ['type' => 'ID'], 'maskedId' => ['type' => 'String'], 'giftcardCode' => ['type' => 'String!']],
            description: 'Remove gift card from cart'
        ),
    ]
)]
class Cart
{
    public ?int $id = null;
    public ?string $maskedId = null;
    public ?int $customerId = null;
    public int $storeId = 1;
    public bool $isActive = true;
    /** @var CartItem[] */
    public array $items = [];
    public CartPrices $prices;
    public ?Address $billingAddress = null;
    public ?Address $shippingAddress = null;
    /** @var array<array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}> */
    public array $availableShippingMethods = [];
    /** @var array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}|null */
    public ?array $selectedShippingMethod = null;
    /** @var array<array{code: string, title: string}> */
    public array $availablePaymentMethods = [];
    /** @var array{code: string, title: string}|null */
    public ?array $selectedPaymentMethod = null;
    /** @var array{code: string, discountAmount: float}|null */
    public ?array $appliedCoupon = null;
    /** @var array<array{code: string, balance: float, appliedAmount: float}> */
    public array $appliedGiftcards = [];
    public string $currency = 'AUD';
    public int $itemsCount = 0;
    public float $itemsQty = 0;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct()
    {
        $this->prices = new CartPrices();
    }
}
