<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

namespace Mage\CatalogInventory\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('inventory/read')",
    mahoId: 'inventory',
    mahoSection: 'Catalog',
    mahoOperations: ['read' => 'View Stock', 'write' => 'Update Stock'],
    shortName: 'Stock',
    description: 'Fast inventory / stock update resource',
    processor: StockUpdateProcessor::class,
    operations: [
        new Put(
            uriTemplate: '/inventory',
            security: "is_granted('ROLE_ADMIN') or is_granted('inventory/write')",
            description: 'Update stock for a single product by SKU',
        ),
        new Put(
            uriTemplate: '/inventory/bulk',
            security: "is_granted('ROLE_ADMIN') or is_granted('inventory/write')",
            description: 'Update stock for multiple products (max 100)',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a stock update', security: "is_granted('ROLE_ADMIN') or is_granted('inventory/read')"),
        new QueryCollection(name: 'collection_query', description: 'Get stock updates', security: "is_granted('ROLE_ADMIN') or is_granted('inventory/read')"),
        new Mutation(
            name: 'update',
            description: 'Update stock for a single product by SKU',
            args: [
                'sku' => ['type' => 'String!'],
                'qty' => ['type' => 'Float!'],
                'isInStock' => ['type' => 'Boolean'],
                'manageStock' => ['type' => 'Boolean'],
                'minQty' => ['type' => 'Float'],
                'minSaleQty' => ['type' => 'Float'],
                'maxSaleQty' => ['type' => 'Float'],
                'backorders' => ['type' => 'Int'],
                'notifyStockQty' => ['type' => 'Float'],
                'isQtyDecimal' => ['type' => 'Boolean'],
                'qtyIncrements' => ['type' => 'Float'],
                'enableQtyIncrements' => ['type' => 'Boolean'],
                'useConfigMinQty' => ['type' => 'Boolean'],
                'useConfigMinSaleQty' => ['type' => 'Boolean'],
                'useConfigMaxSaleQty' => ['type' => 'Boolean'],
                'useConfigBackorders' => ['type' => 'Boolean'],
                'useConfigNotifyStockQty' => ['type' => 'Boolean'],
                'useConfigQtyIncrements' => ['type' => 'Boolean'],
                'useConfigEnableQtyInc' => ['type' => 'Boolean'],
                'useConfigManageStock' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('inventory/write')",
        ),
        new Mutation(
            name: 'bulkUpdate',
            description: 'Update stock for multiple products',
            args: [
                'items' => ['type' => 'Iterable!'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('inventory/write')",
        ),
    ],
)]
class StockUpdate extends CrudResource
{
    public const MODEL = 'cataloginventory/stock_item';

    /** Admin ACL gate. Stock changes are gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true)]
    public string $sku = '';

    public float $qty = 0;

    public ?bool $isInStock = null;

    public ?bool $manageStock = null;

    public ?float $minQty = null;

    public ?float $minSaleQty = null;

    public ?float $maxSaleQty = null;

    #[ApiProperty(description: 'Backorders mode: 0 = no, 1 = allow qty below 0, 2 = allow and notify customer')]
    public ?int $backorders = null;

    public ?float $notifyStockQty = null;

    public ?bool $isQtyDecimal = null;

    public ?float $qtyIncrements = null;

    public ?bool $enableQtyIncrements = null;

    public ?bool $useConfigMinQty = null;

    public ?bool $useConfigMinSaleQty = null;

    public ?bool $useConfigMaxSaleQty = null;

    public ?bool $useConfigBackorders = null;

    public ?bool $useConfigNotifyStockQty = null;

    public ?bool $useConfigQtyIncrements = null;

    public ?bool $useConfigEnableQtyInc = null;

    public ?bool $useConfigManageStock = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?float $previousQty = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public bool $success = false;

    /** @var StockUpdate[]|null Bulk results (null for single updates) */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?array $results = null;
}
