<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'Product',
    description: 'Product catalog resource',
    provider: ProductProvider::class,
    // Both groups exposed at resource level so the GraphQL schema includes all
    // detail fields (configurableOptions, variants, mediaGallery, etc.). Per-operation
    // normalizationContext still controls which fields are POPULATED at request time -
    // listings stay light, item fetches return everything. Without product:detail here,
    // ApiPlatform's GraphQL TypeBuilder excludes detail fields from the schema and clients
    // get "Cannot query field X on type Product" errors even when the operation's own
    // context lists product:detail.
    normalizationContext: ['groups' => ['product:read', 'product:detail']],
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
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Creates a new product',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new Put(
            uriTemplate: '/products/{id}',
            processor: ProductProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Updates a product',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
        ),
        new Delete(
            uriTemplate: '/products/{id}',
            processor: ProductProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/delete')",
            description: 'Deletes a product',
        ),
    ],
    graphQlOperations: [
        // Canonical operation names ApiPlatform uses internally to resolve
        // related-resource references (relatedProducts, crosssellProducts, etc.).
        // Without these, FieldsBuilder crashes with "Operation collection_query
        // not found for resource Product" the moment any field references the
        // Product type. Field names exposed by these are `product` / `products`
        // (no compose because the names are the conventional ones).
        new Query(
            name: 'item_query',
            description: 'Get a product by ID (canonical)',
            normalizationContext: ['groups' => ['product:read', 'product:detail']],
            security: 'true',
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get products with filtering and sorting',
            security: 'true',
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
                'sku' => ['type' => 'String', 'description' => 'Exact SKU lookup (returns 0 or 1 product)'],
                'barcode' => ['type' => 'String', 'description' => 'Exact barcode lookup (returns 0 or 1 product)'],
            ],
            extraArgs: [
                'createdFrom' => ['type' => 'String', 'description' => 'Created at or after this UTC date or datetime; a bare date means from 00:00:00'],
                'createdTo' => ['type' => 'String', 'description' => 'Created at or before this UTC date or datetime; a bare date includes the whole day'],
                'updatedSince' => ['type' => 'String', 'description' => 'Updated at or after this UTC date or datetime'],
            ],
        ),
        // Named 'category' → field `categoryProducts` (not `categoryProductsProducts`).
        new QueryCollection(
            security: 'true',
            name: 'category',
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

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Catalog_ProductController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

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
    #[ApiProperty(description: 'Visibility: not_visible, catalog, search, catalog_search')]
    public ?string $visibility = null;

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
    #[ApiProperty(description: 'Special price start date (Y-m-d); empty string clears it')]
    public ?string $specialFromDate = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Special price end date (Y-m-d); empty string clears it')]
    public ?string $specialToDate = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product cost; only returned to admin and API tokens')]
    public ?float $cost = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: '"New product" from date (Y-m-d); empty string clears it')]
    public ?string $newsFromDate = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: '"New product" to date (Y-m-d); empty string clears it')]
    public ?string $newsToDate = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Manufacturer suggested retail price')]
    public ?float $msrp = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'MSRP enabled: 0 = no, 1 = yes, 2 = use config')]
    public ?int $msrpEnabled = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'MSRP actual price display type')]
    public ?int $msrpDisplayActualPriceType = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Final computed price after rules and specials', writable: false, extraProperties: ['computed' => true])]
    public ?float $finalPrice = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Minimum price (useful for bundles/grouped)', writable: false, extraProperties: ['computed' => true])]
    public ?float $minimalPrice = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Currency code for all price fields', writable: false, extraProperties: ['computed' => true])]
    public string $currency = '';

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Tier pricing thresholds', writable: false, extraProperties: ['computed' => true])]
    public array $tierPrices = [];

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Available stock quantity')]
    public ?float $stockQty = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product weight')]
    public ?float $weight = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Product barcode (EAN/UPC)')]
    public ?string $barcode = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Global Trade Item Number (UPC/EAN/ISBN); ignored when the attribute is not installed')]
    public ?string $gtin = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Manufacturer Part Number; ignored when the attribute is not installed')]
    public ?string $mpn = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Country of manufacture (ISO 3166 code)')]
    public ?string $countryOfManufacture = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Whether a gift message is available: 0 = no, 1 = yes, 2 = use config')]
    public ?int $giftMessageAvailable = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Custom options container (container1, container2)')]
    public ?string $optionsContainer = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'SEO meta robots directive')]
    public ?string $metaRobots = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Custom design/theme override')]
    public ?string $customDesign = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Custom design active from date (Y-m-d); empty string clears it')]
    public ?string $customDesignFrom = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Custom design active to date (Y-m-d); empty string clears it')]
    public ?string $customDesignTo = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Custom layout update XML')]
    public ?string $customLayoutUpdate = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Label for the base image')]
    public ?string $imageLabel = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Label for the small image')]
    public ?string $smallImageLabel = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Label for the thumbnail image')]
    public ?string $thumbnailLabel = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Materialized URL rewrite path', writable: false)]
    public ?string $urlPath = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Bundle SKU type: 0 = dynamic, 1 = fixed')]
    public ?int $skuType = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Bundle price type: 0 = dynamic, 1 = fixed')]
    public ?int $priceType = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Bundle weight type: 0 = dynamic, 1 = fixed')]
    public ?int $weightType = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Bundle price view: 0 = price range, 1 = as low as')]
    public ?int $priceView = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Bundle shipment type: 0 = together, 1 = separately')]
    public ?int $shipmentType = null;

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
    #[ApiProperty(description: 'Category IDs this product belongs to')]
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

    /**
     * Related products as a flat array of product summaries. Typed as
     * untyped array (not Product[]) so GraphQL exposes them as Iterable
     * scalar - consistent with configurableOptions / customOptions /
     * mediaGallery and what every consumer currently expects. Typing as
     * Product[] would wrap in a ProductCursorConnection that forces sub-
     * selection (edges/node), breaking bare queries.
     *
     * @var array<int, array<string, mixed>>|null
     */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Related products', writable: false, extraProperties: ['computed' => true])]
    public ?array $relatedProducts = null;

    /** @var array<int, array<string, mixed>>|null Cross-sell product summaries; same Iterable-scalar rationale as $relatedProducts. */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Cross-sell products', writable: false, extraProperties: ['computed' => true])]
    public ?array $crosssellProducts = null;

    /** @var array<int, array<string, mixed>>|null Up-sell product summaries; same Iterable-scalar rationale as $relatedProducts. */
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
    #[ApiProperty(description: 'Section title for downloadable links')]
    public ?string $linksTitle = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Whether links can be purchased individually')]
    public ?bool $linksPurchasedSeparately = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Section title for downloadable samples')]
    public ?string $samplesTitle = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Grouped product associated items', writable: false, extraProperties: ['computed' => true])]
    public array $groupedProducts = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Bundle product options and selections', writable: false, extraProperties: ['computed' => true])]
    public array $bundleOptions = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Gift card type: fixed | range | combined', writable: false, extraProperties: ['computed' => true])]
    public ?string $giftcardType = null;

    /** @var float[] */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Preset amounts (giftcard fixed/combined types)', writable: false, extraProperties: ['computed' => true])]
    public array $giftcardAmounts = [];

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Minimum custom amount (giftcard range/combined)', writable: false, extraProperties: ['computed' => true])]
    public ?float $giftcardMinAmount = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Maximum custom amount (giftcard range/combined)', writable: false, extraProperties: ['computed' => true])]
    public ?float $giftcardMaxAmount = null;

    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Whether a personal message is allowed (giftcard)', writable: false, extraProperties: ['computed' => true])]
    public bool $giftcardIsMessageAllowed = false;

    /** @var array<array{label: string, value: string, code: string}> */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Additional product attributes for specifications tab', writable: false, extraProperties: ['computed' => true])]
    public array $additionalAttributes = [];

    /** @var int[]|null Website IDs for product assignment; populated on item detail reads */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Website IDs for product assignment', extraProperties: ['computed' => true])]
    public ?array $websiteIds = null;

    #[ApiProperty(description: 'Whether product is enabled (write to enable/disable; read reflects saved state)')]
    public ?bool $isActive = null;

    /** @var array|null Stock data keyed by stock item column (qty, is_in_stock, manage_stock, min_qty, backorders, the use_config_* family, ...); camelCase keys accepted */
    #[ApiProperty(description: 'Stock data for write operations', readable: false)]
    public ?array $stockData = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Attribute set ID', extraProperties: ['modelField' => 'attribute_set_id'])]
    public ?int $attributeSetId = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'Tax class ID', extraProperties: ['modelField' => 'tax_class_id'])]
    public ?int $taxClassId = null;

    /** @var array<string, mixed>|null Arbitrary EAV attributes to set: {"attribute_code": value} (write only) */
    #[ApiProperty(description: 'Arbitrary EAV attributes to set: {"attribute_code": value}', readable: false)]
    public ?array $customAttributesWrite = null;

    /** @var array<string, mixed>|null User-defined EAV attribute values keyed by attribute_code (read counterpart of customAttributesWrite) */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'User-defined EAV attribute values keyed by attribute_code; only returned to admin and API tokens', writable: false, extraProperties: ['computed' => true])]
    public ?array $customAttributes = null;

    /** @var array<string, mixed>|null Full inventory settings keyed by cataloginventory_stock_item column */
    #[Groups(['product:detail'])]
    #[ApiProperty(description: 'Inventory settings keyed by stock item column (round-trips with stockData); back-office columns (min_qty, notify_stock_qty, backorders, manage_stock, use_config_*) are only returned to admin and API tokens', writable: false, extraProperties: ['computed' => true])]
    public ?array $stockItem = null;

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

        $dto->currency = \Mage::app()->getStore()->getCurrentCurrencyCode();

        $dto->attributeSetId = $model->getData('attribute_set_id') !== null
            ? (int) $model->getData('attribute_set_id') : null;
        $dto->taxClassId = $model->getData('tax_class_id') !== null
            ? (int) $model->getData('tax_class_id') : null;

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

        // DB stores 'Y-m-d H:i:s' at midnight; expose the date-only form
        $dto->specialFromDate = $dto->specialFromDate ? substr($dto->specialFromDate, 0, 10) : null;
        $dto->specialToDate = $dto->specialToDate ? substr($dto->specialToDate, 0, 10) : null;
        $dto->newsFromDate = $dto->newsFromDate ? substr($dto->newsFromDate, 0, 10) : null;
        $dto->newsToDate = $dto->newsToDate ? substr($dto->newsToDate, 0, 10) : null;
        $dto->customDesignFrom = $dto->customDesignFrom ? substr($dto->customDesignFrom, 0, 10) : null;
        $dto->customDesignTo = $dto->customDesignTo ? substr($dto->customDesignTo, 0, 10) : null;

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
     * Attribute codes already covered by a dedicated DTO property, derived from
     * the field mappings so it never drifts from the class definition. The
     * generic customAttributes map excludes these; system attributes are
     * excluded separately via is_user_defined.
     *
     * @return array<string, true>
     */
    public static function dedicatedAttributeCodes(): array
    {
        static $codes = null;
        if ($codes === null) {
            $codes = [];
            foreach (self::metadata()->fields as $field) {
                $codes[$field->modelField] = true;
            }
        }
        return $codes;
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
