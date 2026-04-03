<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    shortName: 'Invoice',
    description: 'Order invoice resource',
    provider: InvoiceProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/orders/{orderId}/invoices',
            name: 'order_invoices',
            description: 'List invoices for an order',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Get(
            uriTemplate: '/orders/{orderId}/invoices/{id}/pdf',
            name: 'invoice_pdf',
            description: 'Download invoice PDF',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new GetCollection(
            uriTemplate: '/customers/me/orders/{orderId}/invoices',
            name: 'my_order_invoices',
            description: 'List invoices for an authenticated customer\'s order',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Get(
            uriTemplate: '/customers/me/orders/{orderId}/invoices/{id}/pdf',
            name: 'my_invoice_pdf',
            description: 'Download invoice PDF for an authenticated customer\'s order',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
    ],
    extraProperties: [
        'model' => 'sales/order_invoice',
    ],
)]
class Invoice extends CrudResource
{
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false)]
    public float $grandTotal = 0.0;

    #[ApiProperty(writable: false)]
    public ?int $state = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $stateName = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $pdfUrl = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->stateName = match ($dto->state) {
            \Mage_Sales_Model_Order_Invoice::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Invoice::STATE_PAID => 'paid',
            \Mage_Sales_Model_Order_Invoice::STATE_CANCELED => 'canceled',
            default => 'unknown',
        };
    }
}
