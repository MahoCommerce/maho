<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Mage\Checkout\Api\Cart;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Mage\Customer\Api\Address;

#[ApiResource(
    mahoOperations: ['read' => 'View', 'create' => 'Place', 'write' => 'Manage'],
    mahoCustomerScoped: true,
    shortName: 'Order',
    description: 'View order history, place orders at checkout',
    provider: OrderProvider::class,
    processor: OrderProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/orders/{id}',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get an order by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get order collection (admin only)',
        ),
        new GetCollection(
            uriTemplate: '/customers/me/orders',
            name: 'my_orders',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Get current customer order history',
        ),
        new Post(
            uriTemplate: '/orders',
            security: 'true',
            description: 'Place a new order from cart',
        ),
        new Post(
            // The placeholder is named `maskedQuoteId` rather than `id` so
            // API Platform's auto-identifier converter doesn't try to coerce
            // the 32-char hex maskedId into Order.id (which is typed int and
            // would silently truncate to the leading digits).
            uriTemplate: '/guest-carts/{maskedQuoteId}/place-order',
            name: 'place_guest_order',
            uriVariables: [
                'maskedQuoteId' => new Link(fromClass: Cart::class, identifiers: []),
            ],
            requirements: ['maskedQuoteId' => '[a-f0-9]{32}'],
            security: 'true',
            description: 'Place order from guest cart',
        ),
        new Get(
            // Read an order by its human-readable increment id, authenticated
            // by the one-time `guest_access_token` issued at placement
            // (X-Order-Token header). Symmetric counterpart to the standard
            // /orders/{id} Get which uses JWT customer/admin auth. Token is
            // single-use: consumed (column cleared) on successful read.
            //
            // Headless clients (storefronts, mobile apps, kiosks, agents)
            // call this to render an order-confirmation view without
            // requiring the customer to be authenticated.
            uriTemplate: '/orders/{incrementId}/details',
            name: 'get_order_by_token',
            uriVariables: [
                'incrementId' => new Link(fromClass: Order::class, identifiers: []),
            ],
            requirements: ['incrementId' => '[a-zA-Z0-9_-]+'],
            extraProperties: ['no_iri' => true],
            security: 'true',
            description: 'Read an order using the per-order one-time access token (X-Order-Token header)',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an order by ID',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get orders',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Query(
            name: 'order',
            args: ['id' => ['type' => 'ID!']],
            description: 'Get order by ID',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Query(
            name: 'guestOrder',
            args: ['incrementId' => ['type' => 'String!'], 'accessToken' => ['type' => 'String!']],
            description: 'Get guest order by increment ID and access token',
            resolver: CustomQueryResolver::class,
        ),
        new QueryCollection(
            name: 'customerOrders',
            args: ['page' => ['type' => 'Int'], 'pageSize' => ['type' => 'Int'], 'status' => ['type' => 'String']],
            description: 'Get orders for authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'placeOrder',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'paymentMethod' => ['type' => 'String'],
                'shippingMethod' => ['type' => 'String'],
                'guestEmail' => ['type' => 'String'],
                'orderNote' => ['type' => 'String'],
            ],
            description: 'Place order from cart (requires maskedId for guest, or authentication for customer carts)',
        ),
        new Mutation(
            name: 'cancelOrder',
            args: [
                'orderId' => ['type' => 'ID'],
                'incrementId' => ['type' => 'String'],
                'reason' => ['type' => 'String', 'description' => 'Optional cancellation reason'],
            ],
            description: 'Cancel an order',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class Order extends CrudResource
{
    public const MODEL = 'sales/order';

    /** Admin ACL gate for admin-token requests. Mirrors backend Mage_Adminhtml_Sales_OrderController. */
    public const ADMIN_RESOURCE = 'sales/order';

    #[ApiProperty(identifier: true, writable: false, description: 'Order entity ID')]
    public ?int $id = null;

    #[ApiProperty(writable: false, description: 'Human-readable order number (e.g., 100000123)')]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false, description: 'Customer ID, null for guest orders')]
    public ?int $customerId = null;

    #[ApiProperty(writable: false, description: 'Customer email address')]
    public ?string $customerEmail = null;

    #[ApiProperty(writable: false, description: 'Customer first name')]
    public ?string $customerFirstname = null;

    #[ApiProperty(writable: false, description: 'Customer last name')]
    public ?string $customerLastname = null;

    #[ApiProperty(writable: false, description: 'Order status (pending, processing, complete, canceled, etc.)')]
    public ?string $status = null;

    #[ApiProperty(writable: false, description: 'Order state (new, processing, complete, closed, canceled)')]
    public ?string $state = null;

    /**
     * Order line items. Typed as untyped array so GraphQL exposes as Iterable
     * scalar (queryable bare). OrderItem is a plain DTO with no #[ApiResource],
     * so wrapping in IterableCursorConnection breaks: the connection resolver
     * can't materialize edges without a registered Read operation on the
     * element type and returns null edges.
     * @var array<int, array<string, mixed>>
     */
    #[ApiProperty(writable: false, description: 'Order line items', extraProperties: ['computed' => true])]
    public array $items = [];

    #[ApiProperty(readableLink: true, writable: false, description: 'Billing address', extraProperties: ['computed' => true])]
    public ?Address $billingAddress = null;

    #[ApiProperty(readableLink: true, writable: false, description: 'Shipping address', extraProperties: ['computed' => true])]
    public ?Address $shippingAddress = null;

    /** @var array<string, float|null> */
    #[ApiProperty(writable: false, description: 'Order price totals', extraProperties: ['computed' => true])]
    public array $prices = [];

    #[ApiProperty(writable: false, description: 'Payment method code', extraProperties: ['computed' => true])]
    public ?string $paymentMethod = null;

    #[ApiProperty(writable: false, description: 'Payment method display title', extraProperties: ['computed' => true])]
    public ?string $paymentMethodTitle = null;

    #[ApiProperty(writable: false, description: 'Shipping method code (carrier_method)')]
    public ?string $shippingMethod = null;

    #[ApiProperty(writable: false, description: 'Shipping method description')]
    public ?string $shippingDescription = null;

    #[ApiProperty(writable: false, description: 'Applied coupon code')]
    public ?string $couponCode = null;

    #[ApiProperty(writable: false, description: 'Store ID')]
    public int $storeId = 1;

    #[ApiProperty(writable: false, description: 'Order currency code', extraProperties: ['computed' => true])]
    public string $currency = 'AUD';

    #[ApiProperty(writable: false, description: 'Total number of distinct items')]
    public int $totalItemCount = 0;

    #[ApiProperty(writable: false, description: 'Total quantity ordered')]
    public float $totalQtyOrdered = 0;

    #[ApiProperty(writable: false, description: 'Access token for guest order lookup', extraProperties: ['computed' => true])]
    public ?string $accessToken = null;

    #[ApiProperty(writable: false, description: 'One-time HMAC token to create a customer account from a guest order (only set when looking up a guest order whose email has no account yet)', extraProperties: ['computed' => true])]
    public ?string $accountToken = null;

    #[ApiProperty(writable: false, description: 'Change amount for cash payments', extraProperties: ['computed' => true])]
    public ?float $changeAmount = null;

    #[ApiProperty(writable: false, description: 'Order creation date (UTC)')]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false, description: 'Last update date (UTC)')]
    public ?string $updatedAt = null;

    /** @var array<array{note: string|null, createdAt: string, isCustomerNotified: bool, isVisibleOnFront: bool}> */
    #[ApiProperty(writable: false, description: 'Order status change history', extraProperties: ['computed' => true])]
    public array $statusHistory = [];

    /** @var Shipment[] */
    #[ApiProperty(writable: false, description: 'Order shipments', extraProperties: ['computed' => true])]
    public array $shipments = [];

    public function __construct() {}

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->currency = $model->getOrderCurrencyCode() ?: \Mage::app()->getStore()->getDefaultCurrencyCode();
    }
}
