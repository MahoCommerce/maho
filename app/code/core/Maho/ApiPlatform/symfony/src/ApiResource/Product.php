<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\ProductProvider;

#[ApiResource(
    shortName: 'Product',
    description: 'Product catalog resource',
    provider: ProductProvider::class,
    operations: [
        new Get(
            uriTemplate: '/products/{id}',
            description: 'Get a product by ID',
        ),
        new GetCollection(
            uriTemplate: '/products',
            description: 'Get product collection',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'product', description: 'Get a product by ID'),
        new QueryCollection(
            name: 'products',
            args: [
                'search' => ['type' => 'String', 'description' => 'Search query'],
                'categoryId' => ['type' => 'Int', 'description' => 'Filter by category ID'],
                'priceMin' => ['type' => 'Float', 'description' => 'Minimum price filter'],
                'priceMax' => ['type' => 'Float', 'description' => 'Maximum price filter'],
                'sortBy' => ['type' => 'String', 'description' => 'Sort field (name, price, created_at)'],
                'sortDir' => ['type' => 'String', 'description' => 'Sort direction (asc, desc)'],
                'pageSize' => ['type' => 'Int', 'description' => 'Items per page (max 100)'],
                'page' => ['type' => 'Int', 'description' => 'Page number'],
                'attributeFilters' => ['type' => 'String', 'description' => 'JSON-encoded attribute filters: {"brand_id":"10","series":"1877"}'],
            ],
            description: 'Get products with filtering and sorting',
        ),
        new Query(
            name: 'productBySku',
            args: ['sku' => ['type' => 'String!']],
            description: 'Get a product by SKU',
            resolver: CustomQueryResolver::class,
        ),
        new Query(
            name: 'productByBarcode',
            args: ['barcode' => ['type' => 'String!']],
            description: 'Get a product by barcode',
            resolver: CustomQueryResolver::class,
        ),
        new QueryCollection(
            name: 'categoryProducts',
            args: [
                'categoryId' => ['type' => 'Int!', 'description' => 'Category ID'],
                'sortBy' => ['type' => 'String', 'description' => 'Sort field (name, price, position, created_at)'],
                'sortDir' => ['type' => 'String', 'description' => 'Sort direction (asc, desc)'],
                'pageSize' => ['type' => 'Int', 'description' => 'Items per page (max 100)'],
                'page' => ['type' => 'Int', 'description' => 'Page number'],
            ],
            description: 'Get products by category ID',
        ),
    ],
)]
class Product
{
    #[ApiProperty(description: 'Product entity ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Product SKU')]
    public string $sku = '';

    #[ApiProperty(description: 'URL key for SEO-friendly URLs')]
    public ?string $urlKey = null;

    #[ApiProperty(description: 'SEO meta title')]
    public ?string $metaTitle = null;

    #[ApiProperty(description: 'SEO meta description')]
    public ?string $metaDescription = null;

    #[ApiProperty(description: 'SEO meta keywords')]
    public ?string $metaKeywords = null;

    #[ApiProperty(description: 'Product name')]
    public string $name = '';

    #[ApiProperty(description: 'Full product description (HTML)')]
    public ?string $description = null;

    #[ApiProperty(description: 'Short product description')]
    public ?string $shortDescription = null;

    #[ApiProperty(description: 'Product type: simple, configurable, grouped, bundle, virtual')]
    public string $type = 'simple';

    #[ApiProperty(description: 'Product status: enabled or disabled')]
    public string $status = 'enabled';

    #[ApiProperty(description: 'Visibility: not_visible, catalog, search, catalog_search')]
    public string $visibility = 'catalog_search';

    #[ApiProperty(description: 'Stock status: in_stock or out_of_stock')]
    public string $stockStatus = 'in_stock';

    #[ApiProperty(description: 'Base price')]
    public ?float $price = null;

    #[ApiProperty(description: 'Special/sale price')]
    public ?float $specialPrice = null;

    #[ApiProperty(description: 'Final computed price after rules and specials')]
    public ?float $finalPrice = null;

    #[ApiProperty(description: 'Available stock quantity')]
    public ?float $stockQty = null;

    #[ApiProperty(description: 'Product weight')]
    public ?float $weight = null;

    #[ApiProperty(description: 'Product barcode (EAN/UPC)')]
    public ?string $barcode = null;

    #[ApiProperty(description: 'Main product image URL')]
    public ?string $imageUrl = null;

    #[ApiProperty(description: 'Small product image URL')]
    public ?string $smallImageUrl = null;

    #[ApiProperty(description: 'Thumbnail image URL')]
    public ?string $thumbnailUrl = null;

    #[ApiProperty(description: 'Category IDs this product belongs to')]
    public array $categoryIds = [];

    #[ApiProperty(description: 'Creation date (UTC)')]
    public ?string $createdAt = null;

    #[ApiProperty(description: 'Last update date (UTC)')]
    public ?string $updatedAt = null;

    /**
     * Configurable product options (attributes with available values)
     * Structure: [['id' => 298, 'code' => 'size', 'label' => 'Size', 'values' => [['id' => 10, 'label' => 'Small'], ...]]]
     */
    #[ApiProperty(description: 'Configurable product options with available values (detail only)')]
    public array $configurableOptions = [];

    /**
     * Configurable product variants (child products)
     * Structure: [['id' => 123, 'sku' => 'ABC-S', 'price' => 99.95, 'stockQty' => 10, 'attributes' => ['size' => 10]]]
     */
    #[ApiProperty(description: 'Configurable product variants/children (detail only)')]
    public array $variants = [];

    #[ApiProperty(description: 'Whether product has required custom options')]
    public bool $hasRequiredOptions = false;

    /**
     * Custom options (e.g., String, Tension, Cover for tennis racquets)
     * Structure: [['id' => 10061, 'title' => 'String', 'type' => 'drop_down', 'required' => true, 'values' => [...]]]
     */
    #[ApiProperty(description: 'Custom options like string, tension, cover (detail only)')]
    public array $customOptions = [];

    /**
     * Media gallery images (detail only)
     * Structure: [['url' => 'https://...', 'label' => 'Front view', 'position' => 1]]
     * @var array<array{url: string, label: string|null, position: int}>
     */
    #[ApiProperty(description: 'Product media gallery images (detail only)')]
    public array $mediaGallery = [];

    /** @var Product[]|null Related products (detail only, null for listings) */
    #[ApiProperty(description: 'Related products (detail only)')]
    public ?array $relatedProducts = null;

    /** @var Product[]|null Cross-sell products (detail only, null for listings) */
    #[ApiProperty(description: 'Cross-sell products (detail only)')]
    public ?array $crosssellProducts = null;

    /** @var Product[]|null Up-sell products (detail only, null for listings) */
    #[ApiProperty(description: 'Up-sell products (detail only)')]
    public ?array $upsellProducts = null;

    #[ApiProperty(description: 'Number of approved reviews')]
    public int $reviewCount = 0;

    #[ApiProperty(description: 'Average rating on 1-5 scale, null if no reviews')]
    public ?float $averageRating = null;

    /**
     * Downloadable product links (detail only)
     * Structure: [['id' => 20, 'title' => 'PDF Download', 'price' => 0.0, 'sortOrder' => 0, 'numberOfDownloads' => 0, 'sampleUrl' => null]]
     */
    #[ApiProperty(description: 'Downloadable links (detail only, for downloadable products)')]
    public array $downloadableLinks = [];

    #[ApiProperty(description: 'Section title for downloadable links (e.g. "Check Items to Download")')]
    public ?string $linksTitle = null;

    #[ApiProperty(description: 'Whether links can be purchased individually (shows checkboxes)')]
    public ?bool $linksPurchasedSeparately = null;

    /**
     * Grouped product associated items (detail only)
     * Structure: [['id' => 10, 'sku' => 'ABC', 'name' => 'Child', 'price' => 9.95, 'finalPrice' => 9.95, 'imageUrl' => '...', 'inStock' => true, 'stockQty' => 50, 'defaultQty' => 0, 'position' => 1]]
     */
    #[ApiProperty(description: 'Grouped product associated items (detail only)')]
    public array $groupedProducts = [];

    /**
     * Bundle product options with selections (detail only)
     * Structure: [['id' => 1, 'title' => 'Option', 'type' => 'radio', 'required' => true, 'position' => 1, 'selections' => [...]]]
     */
    #[ApiProperty(description: 'Bundle product options and selections (detail only)')]
    public array $bundleOptions = [];
}
