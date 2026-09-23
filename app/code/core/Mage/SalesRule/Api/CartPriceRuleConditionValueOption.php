<?php

/**
 * A page of the value options of one attribute of a condition type.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

// The cart-price-rules/read grant of CartPriceRule gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    shortName: 'CartPriceRuleConditionValueOption',
    description: 'Value options of cart price rule conditions',
    provider: CartPriceRuleConditionValueOptionProvider::class,
    operations: [
        new Get(
            uriTemplate: '/cart-price-rules/condition-value-options',
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
            description: 'Get a page of the value options of an attribute of a condition type in the condition metadata. Query: type and attribute (required), search, page, itemsPerPage (at most 100), values (labels of these comma-separated values), parentId (the child categories of a category, for the category chooser)',
        ),
    ],
    graphQlOperations: [],
)]
class CartPriceRuleConditionValueOption extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_QuoteController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Condition type')]
    public string $type = '';

    #[ApiProperty(description: 'Attribute code')]
    public string $attribute = '';

    /** @var list<array<string, mixed>> */
    #[ApiProperty(description: 'Options: value, label, and group for an option of a group. Categories also have path and hasChildren')]
    public array $items = [];

    public int $totalItems = 0;

    public int $page = 1;

    public int $itemsPerPage = 20;
}
