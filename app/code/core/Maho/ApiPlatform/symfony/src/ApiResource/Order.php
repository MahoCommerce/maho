<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\State\Provider\OrderProvider;
use Maho\ApiPlatform\State\Processor\OrderProcessor;

#[ApiResource(
    shortName: 'Order',
    description: 'Order resource',
    provider: OrderProvider::class,
    processor: OrderProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/orders/{id}',
            description: 'Get an order by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders',
            description: 'Get order collection (admin only)',
        ),
        new GetCollection(
            uriTemplate: '/customers/me/orders',
            name: 'my_orders',
            description: 'Get current customer order history',
        ),
        new Post(
            uriTemplate: '/orders',
            description: 'Place a new order from cart',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'order',
            args: ['id' => ['type' => 'ID!']],
            description: 'Get order by ID',
        ),
        new Query(
            name: 'guestOrder',
            args: ['incrementId' => ['type' => 'String!'], 'accessToken' => ['type' => 'String!']],
            description: 'Get guest order by increment ID and access token',
        ),
        new QueryCollection(
            name: 'customerOrders',
            args: ['page' => ['type' => 'Int'], 'pageSize' => ['type' => 'Int'], 'status' => ['type' => 'String']],
            description: 'Get orders for authenticated customer',
        ),
        new Mutation(
            name: 'placeOrder',
            args: [
                'cartId' => ['type' => 'ID'],
                'maskedId' => ['type' => 'String'],
                'paymentMethod' => ['type' => 'String'],
                'shippingMethod' => ['type' => 'String'],
                'customerId' => ['type' => 'ID'],
            ],
            description: 'Place order from cart',
        ),
        new Mutation(
            name: 'cancelOrder',
            args: [
                'orderId' => ['type' => 'ID'],
                'incrementId' => ['type' => 'String'],
                'reason' => ['type' => 'String', 'description' => 'Optional cancellation reason'],
            ],
            description: 'Cancel an order',
        ),
        new Mutation(
            name: 'placeOrderWithSplitPayments',
            args: [
                'cartId' => ['type' => 'ID!'],
                'maskedId' => ['type' => 'String'],
                'registerId' => ['type' => 'Int'],
                'payments' => ['type' => '[PaymentInput!]!'],
            ],
            description: 'Place order with split payment methods (POS)',
        ),
        new Mutation(
            name: 'recordPayment',
            args: [
                'orderId' => ['type' => 'ID!'],
                'amount' => ['type' => 'Float!'],
                'method' => ['type' => 'String', 'description' => 'Payment method code (default: cash)'],
                'registerId' => ['type' => 'Int'],
                'transactionId' => ['type' => 'String'],
                'terminalId' => ['type' => 'String'],
                'cardType' => ['type' => 'String'],
                'cardLast4' => ['type' => 'String'],
                'authCode' => ['type' => 'String'],
            ],
            description: 'Record a payment against an order',
        ),
        new Query(
            name: 'orderPayments',
            args: ['orderId' => ['type' => 'ID!']],
            description: 'Get all POS payments for an order',
        ),
        new Query(
            name: 'orderPaymentSummary',
            args: ['orderId' => ['type' => 'ID!']],
            description: 'Get payment summary grouped by method',
        ),
    ],
)]
class Order
{
    #[ApiProperty(description: 'Order entity ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Human-readable order number (e.g., 100000123)')]
    public ?string $incrementId = null;

    #[ApiProperty(description: 'Customer ID, null for guest orders')]
    public ?int $customerId = null;

    #[ApiProperty(description: 'Customer email address')]
    public ?string $customerEmail = null;

    #[ApiProperty(description: 'Customer first name')]
    public ?string $customerFirstname = null;

    #[ApiProperty(description: 'Customer last name')]
    public ?string $customerLastname = null;

    #[ApiProperty(description: 'Order status (pending, processing, complete, canceled, etc.)')]
    public ?string $status = null;

    #[ApiProperty(description: 'Order state (new, processing, complete, closed, canceled)')]
    public ?string $state = null;

    /** @var OrderItem[] */
    #[ApiProperty(description: 'Order line items')]
    public array $items = [];

    #[ApiProperty(readableLink: true, description: 'Billing address')]
    public ?Address $billingAddress = null;

    #[ApiProperty(readableLink: true, description: 'Shipping address')]
    public ?Address $shippingAddress = null;

    #[ApiProperty(description: 'Order price totals')]
    public OrderPrices $prices;

    #[ApiProperty(description: 'Payment method code')]
    public ?string $paymentMethod = null;

    #[ApiProperty(description: 'Payment method display title')]
    public ?string $paymentMethodTitle = null;

    #[ApiProperty(description: 'Shipping method code (carrier_method)')]
    public ?string $shippingMethod = null;

    #[ApiProperty(description: 'Shipping method description')]
    public ?string $shippingDescription = null;

    #[ApiProperty(description: 'Applied coupon code')]
    public ?string $couponCode = null;

    #[ApiProperty(description: 'Store ID')]
    public int $storeId = 1;

    #[ApiProperty(description: 'Order currency code')]
    public string $currency = 'AUD';

    #[ApiProperty(description: 'Total number of distinct items')]
    public int $totalItemCount = 0;

    #[ApiProperty(description: 'Total quantity ordered')]
    public float $totalQtyOrdered = 0;

    #[ApiProperty(description: 'Access token for guest order lookup')]
    public ?string $accessToken = null;

    #[ApiProperty(description: 'Change amount for cash payments')]
    public ?float $changeAmount = null;

    #[ApiProperty(description: 'Order creation date (UTC)')]
    public ?string $createdAt = null;

    #[ApiProperty(description: 'Last update date (UTC)')]
    public ?string $updatedAt = null;

    /** @var array<array{note: string|null, createdAt: string, isCustomerNotified: bool, isVisibleOnFront: bool}> */
    #[ApiProperty(description: 'Order status change history')]
    public array $statusHistory = [];

    /** @var Shipment[] */
    #[ApiProperty(description: 'Order shipments')]
    public array $shipments = [];

    public function __construct()
    {
        $this->prices = new OrderPrices();
    }
}
