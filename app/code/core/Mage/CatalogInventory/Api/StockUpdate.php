<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogInventory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\CatalogInventory\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Mutation;

#[ApiResource(
    shortName: 'StockUpdate',
    description: 'Fast inventory / stock update resource',
    processor: StockUpdateProcessor::class,
    operations: [
        new Put(
            uriTemplate: '/inventory',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Update stock for a single product by SKU',
        ),
        new Put(
            uriTemplate: '/inventory/bulk',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Update stock for multiple products (max 100)',
        ),
    ],
    graphQlOperations: [
        new Mutation(
            name: 'updateStock',
            description: 'Update stock for a single product by SKU',
            args: [
                'sku' => ['type' => 'String!'],
                'qty' => ['type' => 'Float!'],
                'isInStock' => ['type' => 'Boolean'],
                'manageStock' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'updateStockBulk',
            description: 'Update stock for multiple products',
            args: [
                'items' => ['type' => 'Iterable!'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class StockUpdate extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public string $sku = '';

    public float $qty = 0;

    public ?bool $isInStock = null;

    public ?bool $manageStock = null;

    #[ApiProperty(writable: false)]
    public ?float $previousQty = null;

    #[ApiProperty(writable: false)]
    public bool $success = false;

    /** @var StockUpdate[]|null Bulk results (null for single updates) */
    #[ApiProperty(writable: false)]
    public ?array $results = null;
}
