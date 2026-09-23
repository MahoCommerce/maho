<?php

/**
 * The condition types, operators and options that the condition trees of cart price rules accept.
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
    shortName: 'CartPriceRuleConditionMetadata',
    description: 'Condition types, operators and value options of cart price rule trees',
    provider: CartPriceRuleConditionMetadataProvider::class,
    operations: [
        new Get(
            uriTemplate: '/cart-price-rules/condition-metadata',
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
            description: 'Get the document that describes the conditions and actions trees of cart price rules, with labels in the admin locale of the caller. Query: knownVersion (when it is equal to the current version, the response has only version, unchanged and scope)',
        ),
    ],
    graphQlOperations: [],
)]
class CartPriceRuleConditionMetadata extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_QuoteController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Hash of the document without the scope part')]
    public string $version = '';

    #[ApiProperty(description: 'Locale of the labels')]
    public string $locale = '';

    #[ApiProperty(description: 'True when the version is equal to the knownVersion query parameter. The response then has no other document parts than scope.')]
    public ?bool $unchanged = null;

    /** @var array<string, string> */
    #[ApiProperty(description: 'Root condition type of each tree: conditions and actions')]
    public array $roots = [];

    /** @var array<string, array<string, mixed>> */
    #[ApiProperty(description: 'Description of each condition type: kind (combine or leaf), label, sentence, aggregator, value, attributes with their operators and options, children, valueless')]
    public array $types = [];

    /** @var array<string, mixed> */
    #[ApiProperty(description: 'Options of the rule fields: simpleAction, couponType, simpleFreeShipping, couponFormats, couponDefaults')]
    public array $rule = [];

    /** @var array<string, list<array<string, mixed>>> */
    #[ApiProperty(description: 'Websites and stores that the caller can use, and all customer groups')]
    public array $scope = [];
}
