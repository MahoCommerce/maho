<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
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
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/read')",
            description: 'Get a credit memo by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders/{orderId}/credit-memos',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/read')",
            description: 'Get credit memos for an order',
        ),
        new Post(
            uriTemplate: '/orders/{orderId}/credit-memos',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/create')",
            description: 'Create a credit memo / refund for an order',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a credit memo by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get all credit memos',
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/read')",
        ),
        new QueryCollection(
            // Named 'order' so ApiPlatform's appended shortName yields the field
            // `orderCreditMemos`, not a stuttering `orderCreditMemosCreditMemos`.
            name: 'order',
            description: 'Get credit memos for a specific order',
            extraArgs: ['orderId' => ['type' => 'Int!']],
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/read')",
        ),
        new Mutation(
            name: 'create',
            description: 'Create a credit memo / refund for an order',
            args: [
                'orderId' => ['type' => 'Int!'],
                'items' => ['type' => 'Iterable'],
                'comment' => ['type' => 'String'],
                'adjustmentPositive' => ['type' => 'Float'],
                'adjustmentNegative' => ['type' => 'Float'],
                'offlineRefund' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('credit-memos/create')",
        ),
    ],
)]
class CreditMemo extends CrudResource
{
    public const MODEL = 'sales/order_creditmemo';

    /** Admin ACL gate. References backend abstract controller's constant. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Controller_Sales_Creditmemo::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false)]
    public ?int $invoiceId = null;

    #[ApiProperty(writable: false)]
    public ?int $storeId = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $orderIncrementId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $state = null;

    #[ApiProperty(description: 'Currency code for all amount fields', writable: false, extraProperties: ['computed' => true])]
    public string $currency = '';

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
    public float $subtotalInclTax = 0;

    #[ApiProperty(writable: false)]
    public float $shippingInclTax = 0;

    #[ApiProperty(writable: false)]
    public float $shippingTaxAmount = 0;

    #[ApiProperty(writable: false)]
    public float $hiddenTaxAmount = 0;

    #[ApiProperty(writable: false)]
    public float $baseSubtotal = 0;

    #[ApiProperty(writable: false)]
    public float $baseTaxAmount = 0;

    #[ApiProperty(writable: false)]
    public float $baseShippingAmount = 0;

    #[ApiProperty(writable: false)]
    public float $baseDiscountAmount = 0;

    #[ApiProperty(writable: false)]
    public float $adjustment = 0;

    #[ApiProperty(writable: false)]
    public float $baseAdjustment = 0;

    #[ApiProperty(writable: false)]
    public float $adjustmentPositive = 0;

    #[ApiProperty(writable: false)]
    public float $adjustmentNegative = 0;

    #[ApiProperty(writable: false)]
    public ?string $transactionId = null;

    #[ApiProperty(writable: false)]
    public ?int $creditmemoStatus = null;

    #[ApiProperty(writable: false)]
    public bool $emailSent = false;

    #[ApiProperty(writable: false)]
    public ?string $orderCurrencyCode = null;

    #[ApiProperty(writable: false)]
    public ?string $baseCurrencyCode = null;

    #[ApiProperty(writable: false)]
    public ?string $discountDescription = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    /** @var array<int, array<string, mixed>> Credit memo line items; CreditMemoItem is a plain DTO so kept as Iterable scalar (see Cart.items / Order.items rationale). */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $items = [];

    /** First comment's text, kept for backward compatibility; see $comments for the full list. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $comment = null;

    /** @var array<int, array<string, mixed>> Credit memo comments; plain arrays, same Iterable rationale as $items. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $comments = [];

    public static function afterLoad(self $dto, object $model): void
    {
        $stateMap = [
            \Mage_Sales_Model_Order_Creditmemo::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED => 'refunded',
            \Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED => 'canceled',
        ];
        $dto->state = $stateMap[(int) $model->getState()] ?? 'unknown';

        // Fall back to the credit memo's own store, never the viewer's display
        // currency: these amounts were fixed at placement and must read the
        // same for everyone.
        $dto->currency = $model->getOrderCurrencyCode() ?: $model->getStore()->getBaseCurrencyCode();

        if ($model->hasData('_preloaded_order_increment_id')) {
            $dto->orderIncrementId = $model->getData('_preloaded_order_increment_id');
        } else {
            $order = $model->getOrder();
            $dto->orderIncrementId = $order ? $order->getIncrementId() : null;
        }

        $dto->items = [];
        foreach ($model->getData('_preloaded_items') ?? $model->getAllItems() as $item) {
            $itemDto = new CreditMemoItem();
            $itemDto->id = (int) $item->getId();
            $itemDto->orderItemId = (int) $item->getOrderItemId();
            $itemDto->productId = $item->getProductId() !== null ? (int) $item->getProductId() : null;
            $itemDto->sku = $item->getSku() ?? '';
            $itemDto->name = $item->getName() ?? '';
            $itemDto->description = $item->getDescription();
            $itemDto->qty = (float) $item->getQty();
            $itemDto->price = (float) $item->getPrice();
            $itemDto->priceInclTax = (float) $item->getPriceInclTax();
            $itemDto->rowTotal = (float) $item->getRowTotal();
            $itemDto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
            $itemDto->taxAmount = (float) $item->getTaxAmount();
            $itemDto->discountAmount = (float) $item->getDiscountAmount();
            $itemDto->basePrice = (float) $item->getBasePrice();
            $itemDto->baseRowTotal = (float) $item->getBaseRowTotal();
            $itemDto->baseTaxAmount = (float) $item->getBaseTaxAmount();
            $itemDto->baseDiscountAmount = (float) $item->getBaseDiscountAmount();
            $itemDto->backToStock = (bool) $item->getBackToStock();
            $dto->items[] = $itemDto;
        }

        $dto->comments = [];
        foreach ($model->getData('_preloaded_comments') ?? $model->getCommentsCollection() as $comment) {
            $dto->comments[] = [
                'id' => (int) $comment->getId(),
                'comment' => $comment->getComment(),
                'createdAt' => $comment->getCreatedAt(),
                'isCustomerNotified' => (bool) $comment->getIsCustomerNotified(),
                'isVisibleOnFront' => (bool) $comment->getIsVisibleOnFront(),
            ];
        }
        $dto->comment = $dto->comments[0]['comment'] ?? null;
    }
}
