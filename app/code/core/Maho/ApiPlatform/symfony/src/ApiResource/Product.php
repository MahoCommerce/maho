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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\State\Provider\ProductProvider;

#[ApiResource(
    shortName: 'Product',
    description: 'Product catalog resource',
    provider: ProductProvider::class,
    operations: [
        new Get(
            uriTemplate: '/products/{id}',
            description: 'Get a product by ID'
        ),
        new GetCollection(
            uriTemplate: '/products',
            description: 'Get product collection'
        ),
    ],
    graphQlOperations: [
        new Query(name: 'product', description: 'Get a product by ID'),
        new QueryCollection(name: 'products', description: 'Get products'),
        new Query(
            name: 'productBySku',
            args: ['sku' => ['type' => 'String!']],
            description: 'Get a product by SKU'
        ),
        new Query(
            name: 'productByBarcode',
            args: ['barcode' => ['type' => 'String!']],
            description: 'Get a product by barcode'
        ),
    ]
)]
class Product
{
    public ?int $id = null;
    public string $sku = '';
    public string $name = '';
    public ?string $description = null;
    public ?string $shortDescription = null;
    public string $type = 'simple';
    public string $status = 'enabled';
    public string $visibility = 'catalog_search';
    public string $stockStatus = 'in_stock';
    public ?float $price = null;
    public ?float $specialPrice = null;
    public ?float $finalPrice = null;
    public ?float $stockQty = null;
    public ?float $weight = null;
    public ?string $barcode = null;
    public ?string $imageUrl = null;
    public ?string $smallImageUrl = null;
    public ?string $thumbnailUrl = null;
    public array $categoryIds = [];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Configurable product options (attributes with available values)
     * Structure: [['id' => 298, 'code' => 'size', 'label' => 'Size', 'values' => [['id' => 10, 'label' => 'Small'], ...]]]
     */
    public array $configurableOptions = [];

    /**
     * Configurable product variants (child products)
     * Structure: [['id' => 123, 'sku' => 'ABC-S', 'price' => 99.95, 'stockQty' => 10, 'attributes' => ['size' => 10]]]
     */
    public array $variants = [];

    /**
     * Whether product has required custom options that must be selected
     */
    public bool $hasRequiredOptions = false;

    /**
     * Custom options (e.g., String, Tension, Cover for tennis racquets)
     * Structure: [['id' => 10061, 'title' => 'String', 'type' => 'drop_down', 'required' => true, 'values' => [...]]]
     */
    public array $customOptions = [];

    /**
     * Number of approved reviews for this product
     */
    public int $reviewCount = 0;

    /**
     * Average rating (1-5 scale, null if no reviews)
     */
    public ?float $averageRating = null;
}
