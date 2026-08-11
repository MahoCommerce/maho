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
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoOperations: ['read' => 'View', 'create' => 'Create', 'write' => 'Manage'],
    security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('invoices/read')",
    shortName: 'Invoice',
    description: 'Order invoice resource',
    provider: InvoiceProvider::class,
    processor: InvoiceProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/orders/{orderId}/invoices',
            name: 'order_invoices',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            description: 'List invoices for an order',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('invoices/read')",
        ),
        new Get(
            uriTemplate: '/orders/{orderId}/invoices/{id}/pdf',
            name: 'invoice_pdf',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
                'id' => new Link(identifiers: ['id']),
            ],
            description: 'Download invoice PDF',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('invoices/read')",
        ),
        new GetCollection(
            uriTemplate: '/customers/me/orders/{orderId}/invoices',
            name: 'my_order_invoices',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            description: 'List invoices for an authenticated customer\'s order',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('invoices/read')",
        ),
        new Get(
            uriTemplate: '/customers/me/orders/{orderId}/invoices/{id}/pdf',
            name: 'my_invoice_pdf',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
                'id' => new Link(identifiers: ['id']),
            ],
            description: 'Download invoice PDF for an authenticated customer\'s order',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('invoices/read')",
        ),
        new Post(
            uriTemplate: '/orders/{orderId}/invoices',
            name: 'order_invoice_create',
            uriVariables: ['orderId' => new Link(toProperty: 'orderId')],
            security: "is_granted('ROLE_ADMIN') or is_granted('invoices/create')",
            description: 'Create an invoice for an order (full or partial). Body: items [{orderItemId, qty}] (all invoiceable items if omitted), capture ("online"|"offline"|"not_capture"), comment, notifyCustomer',
        ),
        new Post(
            uriTemplate: '/invoices/{id}/capture',
            name: 'invoice_capture',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('invoices/write')",
            description: 'Capture an open invoice through the order\'s payment gateway',
        ),
        new Post(
            uriTemplate: '/invoices/{id}/void',
            name: 'invoice_void',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('invoices/write')",
            description: 'Void a captured invoice',
        ),
        new Post(
            uriTemplate: '/invoices/{id}/cancel',
            name: 'invoice_cancel',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('invoices/write')",
            description: 'Cancel an open invoice',
        ),
    ],
    // No GraphQL surface: the provider resolves invoices through their order,
    // and auto-generated operations would dangle off the class-level security.
    graphQlOperations: [],
)]
class Invoice extends CrudResource
{
    public const MODEL = 'sales/order_invoice';

    /** Admin ACL gate. References backend abstract controller's constant. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Controller_Sales_Invoice::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $orderIncrementId = null;

    #[ApiProperty(writable: false)]
    public ?int $storeId = null;

    #[ApiProperty(writable: false)]
    public float $grandTotal = 0.0;

    #[ApiProperty(writable: false)]
    public float $subtotal = 0.0;

    #[ApiProperty(writable: false)]
    public float $taxAmount = 0.0;

    #[ApiProperty(writable: false)]
    public float $shippingAmount = 0.0;

    #[ApiProperty(writable: false)]
    public float $discountAmount = 0.0;

    #[ApiProperty(writable: false)]
    public float $subtotalInclTax = 0.0;

    #[ApiProperty(writable: false)]
    public float $shippingInclTax = 0.0;

    #[ApiProperty(writable: false)]
    public float $totalQty = 0.0;

    #[ApiProperty(writable: false)]
    public float $baseGrandTotal = 0.0;

    #[ApiProperty(writable: false)]
    public float $baseSubtotal = 0.0;

    #[ApiProperty(writable: false)]
    public float $baseTaxAmount = 0.0;

    #[ApiProperty(writable: false)]
    public float $baseShippingAmount = 0.0;

    #[ApiProperty(writable: false)]
    public float $baseDiscountAmount = 0.0;

    #[ApiProperty(writable: false)]
    public ?string $transactionId = null;

    #[ApiProperty(description: 'Currency code for all amount fields', writable: false, extraProperties: ['computed' => true])]
    public string $currency = '';

    #[ApiProperty(writable: false)]
    public ?int $state = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $stateName = null;

    #[ApiProperty(writable: false)]
    public bool $emailSent = false;

    #[ApiProperty(writable: false)]
    public bool $canVoidFlag = false;

    #[ApiProperty(writable: false)]
    public bool $isUsedForRefund = false;

    #[ApiProperty(writable: false)]
    public ?string $discountDescription = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $pdfUrl = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    /** @var array<int, InvoiceItem> Invoice line items; plain-DTO elements so kept as Iterable scalar (see Shipment.items rationale). */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $items = [];

    /** @var array<int, array<string, mixed>> Invoice comments; same reason as $items above. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $comments = [];

    public static function afterLoad(self $dto, object $model): void
    {
        // Fall back to the invoice's own store, never the viewer's display
        // currency: these amounts were fixed at placement and must read the
        // same for everyone.
        $dto->currency = $model->getOrderCurrencyCode() ?: $model->getStore()->getBaseCurrencyCode();

        $dto->stateName = match ($dto->state) {
            \Mage_Sales_Model_Order_Invoice::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Invoice::STATE_PAID => 'paid',
            \Mage_Sales_Model_Order_Invoice::STATE_CANCELED => 'canceled',
            default => 'unknown',
        };

        $order = $model->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        $dto->items = [];
        foreach ($model->getData('_preloaded_items') ?? $model->getAllItems() as $item) {
            $itemDto = new InvoiceItem();
            $itemDto->id = (int) $item->getId();
            $itemDto->orderItemId = (int) $item->getOrderItemId();
            $itemDto->productId = $item->getProductId() !== null ? (int) $item->getProductId() : null;
            $itemDto->sku = $item->getSku() ?? '';
            $itemDto->name = $item->getName() ?? '';
            $itemDto->qty = (float) $item->getQty();
            $itemDto->price = (float) $item->getPrice();
            $itemDto->priceInclTax = (float) $item->getPriceInclTax();
            $itemDto->rowTotal = (float) $item->getRowTotal();
            $itemDto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
            $itemDto->taxAmount = (float) $item->getTaxAmount();
            $itemDto->discountAmount = (float) $item->getDiscountAmount();
            $itemDto->basePrice = (float) $item->getBasePrice();
            $itemDto->basePriceInclTax = (float) $item->getBasePriceInclTax();
            $itemDto->baseRowTotal = (float) $item->getBaseRowTotal();
            $itemDto->baseRowTotalInclTax = (float) $item->getBaseRowTotalInclTax();
            $itemDto->baseTaxAmount = (float) $item->getBaseTaxAmount();
            $itemDto->baseDiscountAmount = (float) $item->getBaseDiscountAmount();
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
    }
}
