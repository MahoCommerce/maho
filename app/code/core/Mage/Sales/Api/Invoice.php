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
)]
class Invoice extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    public ?string $incrementId = null;

    public ?int $orderId = null;

    public float $grandTotal = 0.0;

    public ?int $state = null;

    public ?string $stateName = null;

    public ?string $pdfUrl = null;

    public ?string $createdAt = null;
}
