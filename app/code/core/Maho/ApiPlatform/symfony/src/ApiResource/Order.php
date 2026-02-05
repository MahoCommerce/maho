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
    public ?int $id = null;
    public ?string $incrementId = null;
    public ?int $customerId = null;
    public ?string $customerEmail = null;
    public ?string $customerFirstname = null;
    public ?string $customerLastname = null;
    public ?string $status = null;
    public ?string $state = null;
    /** @var OrderItem[] */
    public array $items = [];
    #[ApiProperty(readableLink: true)]
    public ?Address $billingAddress = null;
    #[ApiProperty(readableLink: true)]
    public ?Address $shippingAddress = null;
    public OrderPrices $prices;
    public ?string $paymentMethod = null;
    public ?string $paymentMethodTitle = null;
    public ?string $shippingMethod = null;
    public ?string $shippingDescription = null;
    public ?string $couponCode = null;
    public int $storeId = 1;
    public string $currency = 'AUD';
    public int $totalItemCount = 0;
    public float $totalQtyOrdered = 0;
    public ?string $accessToken = null;
    public ?float $changeAmount = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    /** @var array<array{note: string|null, createdAt: string, isCustomerNotified: bool, isVisibleOnFront: bool}> */
    public array $statusHistory = [];
    /** @var Shipment[] */
    public array $shipments = [];

    public function __construct()
    {
        $this->prices = new OrderPrices();
    }
}
