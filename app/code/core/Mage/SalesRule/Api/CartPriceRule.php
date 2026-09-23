<?php

/**
 * A cart price rule with its conditions and actions trees.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoSection: 'Sales',
    // The processor loads and checks the rule itself, so writes skip the provider read
    mahoSelfResolvingWrites: true,
    shortName: 'CartPriceRule',
    description: 'Cart price rules with their conditions and actions',
    provider: CartPriceRuleProvider::class,
    processor: CartPriceRuleProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/cart-price-rules',
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
            description: 'List cart price rules without their trees. Filters: search (every word must match part of the name, the description or the coupon code), isActive, couponType (none, specific, auto), websiteId, customerGroupId, activeOn (YYYY-MM-DD), code (the exact coupon code), usesAttribute (a product attribute code in the trees), sort (id, name, sortOrder, fromDate, toDate), order (asc, desc)',
        ),
        new Get(
            uriTemplate: '/cart-price-rules/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
            description: 'Get a cart price rule with its conditions and actions trees',
        ),
        new Post(
            uriTemplate: '/cart-price-rules',
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/create')",
            description: 'Create a cart price rule. Required: name, websiteIds, customerGroupIds, and couponCode for couponType "specific". The trees use the types of GET /cart-price-rules/condition-metadata',
            extraProperties: ['maho_mcp' => false],
        ),
        new Put(
            uriTemplate: '/cart-price-rules/{id}',
            requirements: ['id' => '\d+'],
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/write')",
            description: 'Update a cart price rule. Only the fields in the body change. A conditions or actions tree in the body replaces the stored tree, and null resets it to an empty tree',
            extraProperties: ['maho_mcp' => false],
        ),
        new Delete(
            uriTemplate: '/cart-price-rules/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/delete')",
            description: 'Delete a cart price rule and all its coupons',
            extraProperties: ['maho_mcp' => false],
        ),
    ],
    graphQlOperations: [],
)]
class CartPriceRule extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_QuoteController::ADMIN_RESOURCE;

    public const COUPON_TYPE_NONE = 'none';
    public const COUPON_TYPE_SPECIFIC = 'specific';
    public const COUPON_TYPE_AUTO = 'auto';

    private const TREE_SCHEMA = [
        'type' => 'object',
        'description' => 'Condition tree. A node has the keys type, attribute, operator, value, aggregator and conditions (a list of child nodes), and the read-only key label. GET /cart-price-rules/condition-metadata describes the allowed types, attributes, operators and values.',
        'properties' => [
            'type' => ['type' => 'string'],
            'aggregator' => ['type' => 'string', 'enum' => ['all', 'any']],
            'attribute' => ['type' => 'string'],
            'operator' => ['type' => 'string'],
            'value' => ['description' => 'Boolean for a combine, a string, or a list of strings for list operators'],
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object', 'description' => 'A child node with the same keys']],
            'label' => ['type' => 'string', 'readOnly' => true],
        ],
    ];

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Name, at most 255 characters')]
    public ?string $name = null;

    public ?string $description = null;

    #[ApiProperty(description: 'False by default, so that a new rule does not apply before it is complete')]
    public bool $isActive = false;

    /** @var int[] */
    public array $websiteIds = [];

    /** @var int[] */
    public array $customerGroupIds = [];

    #[ApiProperty(description: 'none, specific (one code in couponCode) or auto (generated codes)', openapiContext: ['enum' => ['none', 'specific', 'auto']])]
    public string $couponType = self::COUPON_TYPE_NONE;

    #[ApiProperty(description: 'The code of a rule with couponType "specific"')]
    public ?string $couponCode = null;

    #[ApiProperty(description: 'Uses of each coupon, 0 for no limit')]
    public int $usesPerCoupon = 0;

    #[ApiProperty(description: 'Uses by each customer, 0 for no limit')]
    public int $usesPerCustomer = 0;

    #[ApiProperty(description: 'First day of the rule, YYYY-MM-DD')]
    public ?string $fromDate = null;

    #[ApiProperty(description: 'Last day of the rule, YYYY-MM-DD')]
    public ?string $toDate = null;

    public int $sortOrder = 0;

    public bool $stopRulesProcessing = false;

    public bool $isRss = false;

    #[ApiProperty(description: 'by_percent, by_fixed, cart_fixed or buy_x_get_y', openapiContext: ['enum' => ['by_percent', 'by_fixed', 'cart_fixed', 'buy_x_get_y']])]
    public string $simpleAction = \Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION;

    #[ApiProperty(description: 'At most 100 for by_percent')]
    public float $discountAmount = 0;

    #[ApiProperty(description: 'Maximum quantity that the discount applies to')]
    public ?float $discountQty = null;

    #[ApiProperty(description: 'Quantity step (Buy X)')]
    public int $discountStep = 0;

    public bool $applyToShipping = false;

    #[ApiProperty(description: '0 (no), 1 (matching items) or 2 (whole shipment)')]
    public int $simpleFreeShipping = 0;

    /** @var list<array{storeId: int, label: string}> */
    #[ApiProperty(description: 'Labels by store, storeId 0 for the default label. A write replaces the labels of the stores that the caller can use')]
    public array $storeLabels = [];

    /** @var array<string, mixed>|null */
    #[ApiProperty(description: 'Conditions tree. Lists leave it out', openapiContext: self::TREE_SCHEMA)]
    public ?array $conditions = null;

    /** @var array<string, mixed>|null */
    #[ApiProperty(description: 'Actions tree: the cart items that the discount applies to. Lists leave it out', openapiContext: self::TREE_SCHEMA)]
    public ?array $actions = null;

    #[ApiProperty(writable: false)]
    public int $timesUsed = 0;

    #[ApiProperty(writable: false, description: 'Number of coupons of the rule, the generated coupons included')]
    public int $couponCount = 0;

    #[ApiProperty(writable: false)]
    public ?int $primaryCouponId = null;
}
