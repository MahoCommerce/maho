<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Product',
    description: 'Product catalog resource',
    provider: ProductProvider::class,
    normalizationContext: ['groups' => ['product:read']],
    operations: [
        new Get(
            uriTemplate: '/products/{id}',
            security: 'true',
            description: 'Get a product by ID',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new GetCollection(
            uriTemplate: '/products',
            security: 'true',
            description: 'Get product collection',
        ),
        new Post(
            uriTemplate: '/products',
            processor: ProductProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new product',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new Put(
            uriTemplate: '/products/{id}',
            processor: ProductProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a product',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new Delete(
            uriTemplate: '/products/{id}',
            processor: ProductProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Deletes a product',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'product',
            description: 'Get a product by ID',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
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
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new Query(
            name: 'productByBarcode',
            args: ['barcode' => ['type' => 'String!']],
            description: 'Get a product by barcode',
            resolver: CustomQueryResolver::class,
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
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
class Product extends CrudResource
{
    public const MODEL = 'catalog/product';

    #[Groups(['product:read'])]
    #[ApiProperty(identifier: true, description: 'Product entity ID')]
    public ?int $id = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product SKU')]
    public string $sku = '';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'URL key for SEO-friendly URLs')]
    public ?string $urlKey = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'SEO meta title')]
    public ?string $metaTitle = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'SEO meta description')]
    public ?string $metaDescription = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'SEO meta keywords', extraProperties: ['modelField' => 'meta_keyword'])]
    public ?string $metaKeywords = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Page layout template (e.g. one_column, two_columns_left)')]
    public ?string $pageLayout = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product name')]
    public string $name = '';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Full product description (HTML)')]
    public ?string $description = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Short product description')]
    public ?string $shortDescription = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product type: simple, configurable, grouped, bundle, virtual', extraProperties: ['modelField' => 'type_id'])]
    public string $type = 'simple';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product status: enabled or disabled', writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Visibility: not_visible, catalog, search, catalog_search', writable: false, extraProperties: ['computed' => true])]
    public string $visibility = 'catalog_search';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Stock status: in_stock or out_of_stock', writable: false, extraProperties: ['computed' => true])]
    public string $stockStatus = 'in_stock';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Base price')]
    public ?float $price = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Special/sale price')]
    public ?float $specialPrice = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Final computed price after rules and specials', writable: false, extraProperties: ['computed' => true])]
    public ?float $finalPrice = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Minimum price (useful for bundles/grouped)', writable: false, extraProperties: ['computed' => true])]
    public ?float $minimalPrice = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Tier pricing thresholds', writable: false, extraProperties: ['computed' => true])]
    public array $tierPrices = [];

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Available stock quantity', writable: false, extraProperties: ['computed' => true])]
    public ?float $stockQty = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product weight')]
    public ?float $weight = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product barcode (EAN/UPC)', writable: false, extraProperties: ['computed' => true])]
    public ?string $barcode = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Main product image URL', writable: false, extraProperties: ['computed' => true])]
    public ?string $imageUrl = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Small product image URL', writable: false, extraProperties: ['computed' => true])]
    public ?string $smallImageUrl = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Thumbnail image URL', writable: false, extraProperties: ['computed' => true])]
    public ?string $thumbnailUrl = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Category IDs this product belongs to', writable: false, extraProperties: ['computed' => true])]
    public array $categoryIds = [];

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Creation date (UTC)', writable: false)]
    public ?string $createdAt = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Last update date (UTC)', writable: false)]
    public ?string $updatedAt = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Configurable product options with available values', writable: false, extraProperties: ['computed' => true])]
    public array $configurableOptions = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Configurable product variants/children', writable: false, extraProperties: ['computed' => true])]
    public array $variants = [];

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Whether product has required custom options', extraProperties: ['modelField' => 'required_options'])]
    public bool $hasRequiredOptions = false;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Custom options like string, tension, cover', writable: false, extraProperties: ['computed' => true])]
    public array $customOptions = [];

    /** @var array<array{url: string, label: string|null, position: int}> */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Product media gallery images', writable: false, extraProperties: ['computed' => true])]
    public array $mediaGallery = [];

    /** @var Product[]|null */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Related products', writable: false, extraProperties: ['computed' => true])]
    public ?array $relatedProducts = null;

    /** @var Product[]|null */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Cross-sell products', writable: false, extraProperties: ['computed' => true])]
    public ?array $crosssellProducts = null;

    /** @var Product[]|null */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Up-sell products', writable: false, extraProperties: ['computed' => true])]
    public ?array $upsellProducts = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Number of approved reviews', writable: false, extraProperties: ['computed' => true])]
    public int $reviewCount = 0;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Average rating on 1-5 scale, null if no reviews', writable: false, extraProperties: ['computed' => true])]
    public ?float $averageRating = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Downloadable links (for downloadable products)', writable: false, extraProperties: ['computed' => true])]
    public array $downloadableLinks = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Section title for downloadable links', writable: false, extraProperties: ['computed' => true])]
    public ?string $linksTitle = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Whether links can be purchased individually', writable: false, extraProperties: ['computed' => true])]
    public ?bool $linksPurchasedSeparately = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Grouped product associated items', writable: false, extraProperties: ['computed' => true])]
    public array $groupedProducts = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Bundle product options and selections', writable: false, extraProperties: ['computed' => true])]
    public array $bundleOptions = [];

    /** @var array<array{label: string, value: string, code: string}> */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Additional product attributes for specifications tab', writable: false, extraProperties: ['computed' => true])]
    public array $additionalAttributes = [];

    /** @var int[]|null Website IDs for product assignment (write only) */
    #[ApiProperty(description: 'Website IDs for product assignment', readable: false)]
    public ?array $websiteIds = null;

    #[ApiProperty(description: 'Whether product is enabled', writable: false, extraProperties: ['computed' => true])]
    public bool $isActive = true;

    /** @var array|null Stock data: {qty: float, is_in_stock: bool} */
    #[ApiProperty(description: 'Stock data for write operations', readable: false)]
    public ?array $stockData = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];

    /**
     * Computed fields derivable from the model's own data.
     * Stock, reviews, categories, and detail-only sub-resources are set by the provider.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        $dto->status = (int) $model->getData('status') === \Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            ? 'enabled' : 'disabled';
        $dto->isActive = $dto->status === 'enabled';

        $dto->visibility = match ((int) $model->getData('visibility')) {
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => 'not_visible',
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG => 'catalog',
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH => 'search',
            default => 'catalog_search',
        };

        if ($dto->description !== null) {
            $dto->description = self::filterContent($dto->description);
        }
        if ($dto->shortDescription !== null) {
            $dto->shortDescription = self::filterContent($dto->shortDescription);
        }

        try {
            $dto->finalPrice = $model->getFinalPrice() ? (float) $model->getFinalPrice() : null;
        } catch (\Throwable) {
            $dto->finalPrice = $dto->specialPrice ?? $dto->price;
        }

        if ($dto->price === null || $dto->price === 0.0) {
            $minPrice = $model->getMinimalPrice() ?: $model->getData('min_price');
            if (!$minPrice && in_array($dto->type, ['grouped', 'bundle'])) {
                $minPrice = self::getGroupedMinPrice($model);
            }
            if ($minPrice) {
                $dto->price = (float) $minPrice;
                if ($dto->finalPrice === null || $dto->finalPrice === 0.0) {
                    $dto->finalPrice = (float) $minPrice;
                }
            }
        }

        $minimalPrice = $model->getMinimalPrice() ?: $model->getData('min_price');
        if (!$minimalPrice && in_array($dto->type, ['bundle', 'grouped'])) {
            $minimalPrice = self::getGroupedMinPrice($model);
        }
        if ($minimalPrice) {
            $dto->minimalPrice = (float) $minimalPrice;
        }

        $dto->barcode = $model->getData('barcode') ?: null;

        static $mediaConfig = null;
        $mediaConfig ??= \Mage::getModel('catalog/product_media_config');

        foreach (['image' => 'imageUrl', 'small_image' => 'smallImageUrl', 'thumbnail' => 'thumbnailUrl'] as $field => $prop) {
            $value = $model->getData($field);
            if ($value && $value !== 'no_selection') {
                $dto->$prop = $mediaConfig->getMediaUrl($value);
            }
        }
    }

    private static function getGroupedMinPrice(object $product): ?float
    {
        try {
            $typeInstance = $product->getTypeInstance(true);
            $associated = $typeInstance->getAssociatedProducts($product);
            $prices = [];
            foreach ($associated as $child) {
                $price = (float) $child->getFinalPrice();
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
            return empty($prices) ? null : min($prices);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Create a Product DTO from an associative array.
     * Only sets properties that exist on the class; unknown keys are ignored.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        return $dto;
    }
}
