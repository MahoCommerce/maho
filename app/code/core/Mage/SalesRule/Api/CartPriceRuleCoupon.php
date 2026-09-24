<?php

/**
 * A coupon of a cart price rule.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;

// The grants of CartPriceRule give access, so this uses the plain API Platform
// attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
    shortName: 'CartPriceRuleCoupon',
    description: 'Coupons of a cart price rule',
    provider: CartPriceRuleCouponProvider::class,
    processor: CartPriceRuleCouponProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/cart-price-rules/{ruleId}/coupons',
            uriVariables: ['ruleId' => new Link(fromClass: CartPriceRule::class, identifiers: ['id'])],
            requirements: ['ruleId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/read')",
            description: 'List the coupons of a cart price rule, newest first. Filters: search (every word must match part of the code), isUsed, isPrimary',
        ),
        new Post(
            uriTemplate: '/cart-price-rules/{ruleId}/coupons/generate',
            name: 'cart_price_rule_coupons_generate',
            uriVariables: ['ruleId' => new Link(fromClass: CartPriceRule::class, identifiers: ['id'])],
            requirements: ['ruleId' => '\d+'],
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/write')",
            description: 'Generate coupons for a rule with couponType "auto". Body: qty (1 to 1000), length (1 to 32), format (alphanum, alpha, num), prefix, suffix (at most 32 of A-Z, a-z, 0-9, _ and -), dash (0 to length). The fields that the body leaves out get the defaults of the configuration. Response: generatedCount and coupons',
            extraProperties: ['maho_mcp' => false],
        ),
        new Delete(
            uriTemplate: '/cart-price-rules/{ruleId}/coupons/{couponId}',
            name: 'cart_price_rule_coupon_delete',
            uriVariables: [
                'ruleId' => new Link(fromClass: CartPriceRule::class, identifiers: ['id']),
                'couponId' => new Link(fromClass: CartPriceRuleCoupon::class, identifiers: ['id']),
            ],
            requirements: ['ruleId' => '\d+', 'couponId' => '\d+'],
            read: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/delete')",
            description: 'Delete a generated coupon of a rule. The primary coupon changes only with the rule',
            extraProperties: ['maho_mcp' => false],
        ),
        new Post(
            uriTemplate: '/cart-price-rules/{ruleId}/coupons/mass-delete',
            name: 'cart_price_rule_coupons_mass_delete',
            uriVariables: ['ruleId' => new Link(fromClass: CartPriceRule::class, identifiers: ['id'])],
            requirements: ['ruleId' => '\d+'],
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cart-price-rules/delete')",
            description: 'Delete generated coupons of a rule. Body: ids (at most 1000 coupon IDs). IDs of other rules and of the primary coupon are skipped. Response: deletedCount',
            extraProperties: ['maho_mcp' => false],
        ),
    ],
    graphQlOperations: [],
)]
class CartPriceRuleCoupon extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_QuoteController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $code = null;

    #[ApiProperty(description: 'True for the coupon of a rule with couponType "specific"')]
    public bool $isPrimary = false;

    #[ApiProperty(description: '0 for a coupon that the rule or an admin created, 1 for a generated coupon')]
    public int $type = 0;

    public ?int $usageLimit = null;

    public ?int $usagePerCustomer = null;

    public int $timesUsed = 0;

    public ?string $expirationDate = null;

    public ?string $createdAt = null;

    public static function fromCoupon(\Mage_SalesRule_Model_Coupon $coupon): self
    {
        $dto = new self();
        $dto->id = (int) $coupon->getId();
        $dto->code = $coupon->getCode();
        $dto->isPrimary = (bool) $coupon->getIsPrimary();
        $dto->type = (int) $coupon->getType();
        $dto->usageLimit = $coupon->getUsageLimit() ?: null;
        $dto->usagePerCustomer = $coupon->getUsagePerCustomer() ?: null;
        $dto->timesUsed = (int) $coupon->getTimesUsed();
        $expirationDate = $coupon->getData('expiration_date');
        $dto->expirationDate = is_string($expirationDate) && $expirationDate !== '' ? $expirationDate : null;
        $createdAt = $coupon->getData('created_at');
        $dto->createdAt = is_string($createdAt) && $createdAt !== '' ? $createdAt : null;
        return $dto;
    }
}
