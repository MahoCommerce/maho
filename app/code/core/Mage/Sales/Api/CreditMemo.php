<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    shortName: 'CreditMemo',
    description: 'Order credit memo / refund resource',
    provider: CreditMemoProvider::class,
    processor: CreditMemoProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/credit-memos/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get a credit memo by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders/{orderId}/credit-memos',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get credit memos for an order',
        ),
        new Post(
            uriTemplate: '/orders/{orderId}/credit-memos',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Create a credit memo / refund for an order',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a credit memo by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get all credit memos',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'orderCreditMemos',
            description: 'Get credit memos for a specific order',
            args: ['orderId' => ['type' => 'Int!']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'createCreditMemo',
            description: 'Create a credit memo / refund for an order',
            args: [
                'orderId' => ['type' => 'Int!'],
                'items' => ['type' => 'Iterable'],
                'comment' => ['type' => 'String'],
                'adjustmentPositive' => ['type' => 'Float'],
                'adjustmentNegative' => ['type' => 'Float'],
                'offlineRefund' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class CreditMemo extends CrudResource
{
    public const MODEL = 'sales/order_creditmemo';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $orderIncrementId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $state = null;

    #[ApiProperty(writable: false)]
    public float $grandTotal = 0;

    #[ApiProperty(writable: false)]
    public float $baseGrandTotal = 0;

    #[ApiProperty(writable: false)]
    public float $subtotal = 0;

    #[ApiProperty(writable: false)]
    public float $taxAmount = 0;

    #[ApiProperty(writable: false)]
    public float $shippingAmount = 0;

    #[ApiProperty(writable: false)]
    public float $discountAmount = 0;

    #[ApiProperty(writable: false)]
    public float $adjustmentPositive = 0;

    #[ApiProperty(writable: false)]
    public float $adjustmentNegative = 0;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    /** @var CreditMemoItem[] */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $items = [];

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $comment = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $stateMap = [
            \Mage_Sales_Model_Order_Creditmemo::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED => 'refunded',
            \Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED => 'canceled',
        ];
        $dto->state = $stateMap[(int) $model->getState()] ?? 'unknown';

        $order = $model->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        $dto->items = [];
        foreach ($model->getAllItems() as $item) {
            $itemDto = new CreditMemoItem();
            $itemDto->id = (int) $item->getId();
            $itemDto->orderItemId = (int) $item->getOrderItemId();
            $itemDto->sku = $item->getSku() ?? '';
            $itemDto->name = $item->getName() ?? '';
            $itemDto->qty = (float) $item->getQty();
            $itemDto->price = (float) $item->getPrice();
            $itemDto->rowTotal = (float) $item->getRowTotal();
            $itemDto->taxAmount = (float) $item->getTaxAmount();
            $itemDto->discountAmount = (float) $item->getDiscountAmount();
            $itemDto->backToStock = (bool) $item->getBackToStock();
            $dto->items[] = $itemDto;
        }

        $comments = $model->getCommentsCollection();
        if ($comments && $comments->getSize() > 0) {
            $firstComment = $comments->getFirstItem();
            $dto->comment = $firstComment->getComment();
        }
    }
}
