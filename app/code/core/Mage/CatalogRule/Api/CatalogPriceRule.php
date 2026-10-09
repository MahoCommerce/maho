<?php

/**
 * A catalog price rule with its conditions tree.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\ApiPlatform\Metadata\EnumSource;
use Maho\ApiPlatform\Metadata\ValueLists;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use Maho\Config\ApiResource;

#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/read')",
    mahoLabel: 'Catalog Price Rules',
    mahoSection: 'Promotions',
    mahoOperations: ['read' => 'View', 'write' => 'Create, Update & Apply', 'delete' => 'Delete'],
    // The processor loads and checks the rule itself, so writes skip the provider read
    mahoSelfResolvingWrites: true,
    shortName: 'CatalogPriceRule',
    description: 'Catalog price rules: discounts on product prices by website and customer group',
    provider: CatalogPriceRuleProvider::class,
    processor: CatalogPriceRuleProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/catalog-price-rules',
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/read')",
            description: 'List catalog price rules without their conditions tree. Filters: search (every word must match part of the name or the description), isActive, websiteId, customerGroupId, sort (id, name, sortOrder, fromDate, toDate), order (asc, desc)',
        ),
        new Get(
            uriTemplate: '/catalog-price-rules/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/read')",
            description: 'Get a catalog price rule with its conditions tree',
        ),
        new Post(
            uriTemplate: '/catalog-price-rules',
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/write')",
            description: 'Create a catalog price rule. Required: name, websiteIds, customerGroupIds. The rule is inactive by default. '
                . 'Saving a rule does not change product prices: call POST /catalog-price-rules/apply after the rules are complete',
        ),
        new Put(
            uriTemplate: '/catalog-price-rules/{id}',
            requirements: ['id' => '\d+'],
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/write')",
            description: 'Update a catalog price rule. Only the fields in the body change. A conditions tree in the body replaces the stored tree, and null resets it to an empty tree. '
                . 'Saving a rule does not change product prices: call POST /catalog-price-rules/apply after the rules are complete',
        ),
        new Delete(
            uriTemplate: '/catalog-price-rules/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/delete')",
            description: 'Delete a catalog price rule. Call POST /catalog-price-rules/apply afterwards to remove its discounts from product prices',
        ),
        new Post(
            uriTemplate: '/catalog-price-rules/apply',
            name: 'apply_catalog_price_rules',
            status: 200,
            read: false,
            deserialize: false,
            input: CatalogPriceRuleApplyInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('catalog-price-rules/write')",
            description: 'Apply all catalog price rules to the products, as the "Apply Rules" button of the admin does, and refresh the price index. '
                . 'Saving, updating or deleting a rule does not change product prices until this runs. It takes no body and can take a long time on a large catalog. Response: {success: true}',
            openapi: new OpenApiOperation(
                responses: ['200' => new OpenApiResponse(description: 'The rules are applied')],
                summary: 'Apply all catalog price rules',
                description: 'Recalculates the catalog rule prices of every product and refreshes the price index. Saving a rule marks the rules as changed but does not apply them.',
            ),
        ),
    ],
    graphQlOperations: [],
)]
class CatalogPriceRule extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_CatalogController::ADMIN_RESOURCE;

    public const ROOT_CONDITIONS = 'catalogrule/rule_condition_combine';

    public const SIMPLE_ACTIONS = ['by_percent', 'by_fixed', 'to_percent', 'to_fixed'];

    private const TREE_SCHEMA = [
        'type' => 'object',
        'description' => 'Condition tree. A node has the keys type, attribute, operator, value, aggregator and conditions (the list of child nodes, never "children"), and the read-only key label. '
            . 'The root has the type catalogrule/rule_condition_combine. A leaf has the type catalogrule/rule_condition_product and the attribute code of a product attribute with isUsedForPriceRules true; '
            . 'operators are ==, !=, >=, <=, >, <, {} (contains), !{} (does not contain), () (is one of) and !() (is not one of).',
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
    #[ApiProperty(extraProperties: [EnumSource::KEY => 'Maho\ApiPlatform\Metadata\ValueLists::websites'])]
    public array $websiteIds = [];

    /** @var int[] */
    #[ApiProperty(extraProperties: [EnumSource::KEY => 'Maho\ApiPlatform\Metadata\ValueLists::ruleCustomerGroups'])]
    public array $customerGroupIds = [];

    #[ApiProperty(description: 'First day of the rule, YYYY-MM-DD')]
    public ?string $fromDate = null;

    #[ApiProperty(description: 'Last day of the rule, YYYY-MM-DD')]
    public ?string $toDate = null;

    #[ApiProperty(description: 'Rules with a lower sortOrder apply first')]
    public int $sortOrder = 0;

    #[ApiProperty(description: 'by_percent (discount of a percentage of the price), by_fixed (discount of a fixed amount), to_percent (price becomes a percentage of the price) or to_fixed (price becomes a fixed amount)', extraProperties: [EnumSource::KEY => self::SIMPLE_ACTIONS])]
    public string $simpleAction = 'by_percent';

    #[ApiProperty(description: 'Percentage or amount of the action, 0 or more; at most 100 for by_percent and to_percent')]
    public float $discountAmount = 0;

    #[ApiProperty(description: 'Whether the rule also gives a discount on the sub products of a configurable or bundle product')]
    public bool $subIsEnable = false;

    #[ApiProperty(description: 'Action for the sub products, same values as simpleAction, or null', extraProperties: [EnumSource::KEY => self::SIMPLE_ACTIONS])]
    public ?string $subSimpleAction = null;

    #[ApiProperty(description: 'Percentage or amount of the action for the sub products')]
    public float $subDiscountAmount = 0;

    #[ApiProperty(description: 'True stops the rules with a higher sortOrder from applying to a product that this rule matches')]
    public bool $stopRulesProcessing = false;

    /** @var array<string, mixed>|null */
    #[ApiProperty(description: 'Conditions tree: the products that the rule applies to. Lists leave it out', openapiContext: self::TREE_SCHEMA)]
    public ?array $conditions = null;
}
