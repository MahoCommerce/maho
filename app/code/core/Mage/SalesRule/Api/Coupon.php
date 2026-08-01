<?php

/**
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
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    shortName: 'Coupon',
    mahoSection: 'Sales',
    description: 'Coupon / price rule management resource',
    provider: CouponProvider::class,
    processor: CouponProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/read')",
            description: 'Get a coupon by ID',
        ),
        new GetCollection(
            uriTemplate: '/coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/read')",
            description: 'Get all coupons',
        ),
        new Post(
            uriTemplate: '/coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/create')",
            description: 'Create a new coupon with price rule',
        ),
        new Put(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/write')",
            description: 'Update a coupon and its price rule',
        ),
        new Delete(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/delete')",
            description: 'Delete a coupon and its price rule',
        ),
        new Post(
            uriTemplate: '/coupons/validate',
            security: 'true',
            description: 'Validate a coupon code against a cart',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a coupon by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get all coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/read')",
            extraArgs: [
                'code' => ['type' => 'String', 'description' => 'Partial coupon-code match'],
                'is_active' => ['type' => 'Boolean', 'description' => 'Only coupons whose parent rule is active'],
            ],
        ),
        new Mutation(
            name: 'create',
            description: 'Create a new coupon with price rule',
            args: [
                'code' => ['type' => 'String!'],
                'discountType' => ['type' => 'String!'],
                'discountAmount' => ['type' => 'Float!'],
                'description' => ['type' => 'String'],
                'isActive' => ['type' => 'Boolean'],
                'usageLimit' => ['type' => 'Int'],
                'usagePerCustomer' => ['type' => 'Int'],
                'fromDate' => ['type' => 'String'],
                'toDate' => ['type' => 'String'],
                'minimumSubtotal' => ['type' => 'Float'],
                'expirationDate' => ['type' => 'String'],
                'websiteIds' => ['type' => '[Int]'],
                'customerGroupIds' => ['type' => '[Int]'],
                'sortOrder' => ['type' => 'Int'],
                'stopRulesProcessing' => ['type' => 'Boolean'],
                'discountQty' => ['type' => 'Float'],
                'discountStep' => ['type' => 'Int'],
                'simpleFreeShipping' => ['type' => 'Int'],
                'applyToShipping' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/create')",
        ),
        new Mutation(
            name: 'update',
            description: 'Update a coupon and its price rule',
            args: [
                'id' => ['type' => 'Int!'],
                'code' => ['type' => 'String'],
                'discountType' => ['type' => 'String'],
                'discountAmount' => ['type' => 'Float'],
                'description' => ['type' => 'String'],
                'isActive' => ['type' => 'Boolean'],
                'usageLimit' => ['type' => 'Int'],
                'usagePerCustomer' => ['type' => 'Int'],
                'fromDate' => ['type' => 'String'],
                'toDate' => ['type' => 'String'],
                'minimumSubtotal' => ['type' => 'Float'],
                'expirationDate' => ['type' => 'String'],
                'websiteIds' => ['type' => '[Int]'],
                'customerGroupIds' => ['type' => '[Int]'],
                'sortOrder' => ['type' => 'Int'],
                'stopRulesProcessing' => ['type' => 'Boolean'],
                'discountQty' => ['type' => 'Float'],
                'discountStep' => ['type' => 'Int'],
                'simpleFreeShipping' => ['type' => 'Int'],
                'applyToShipping' => ['type' => 'Boolean'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/write')",
        ),
        new DeleteMutation(
            name: 'delete',
            description: 'Delete a coupon and its price rule',
            security: "is_granted('ROLE_ADMIN') or is_granted('coupons/delete')",
        ),
        new Mutation(
            name: 'validate',
            description: 'Validate a coupon code',
            args: [
                'code' => ['type' => 'String!'],
                'cartId' => ['type' => 'Int'],
            ],
            security: "is_granted('ROLE_CUSTOMER') or is_granted('coupons/write')",
        ),
    ],
)]
class Coupon extends CrudResource
{
    public const MODEL = 'salesrule/coupon';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Promo_QuoteController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Promo_QuoteController::ADMIN_RESOURCE;

    private const DISCOUNT_TYPE_MAP = [
        'by_percent' => 'percent',
        'by_fixed' => 'fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $code = null;

    #[ApiProperty(writable: false)]
    public ?int $ruleId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $ruleName = null;

    /** percent, fixed, cart_fixed, buy_x_get_y */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?string $discountType = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?float $discountAmount = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?string $description = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public bool $isActive = true;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?int $usageLimit = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?int $usagePerCustomer = null;

    #[ApiProperty(writable: false)]
    public int $timesUsed = 0;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?string $fromDate = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?string $toDate = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?float $minimumSubtotal = null;

    /** Per-coupon expiry (salesrule_coupon.expiration_date), distinct from the rule-level toDate */
    public ?string $expirationDate = null;

    #[ApiProperty(writable: false)]
    public ?int $type = null;

    #[ApiProperty(writable: false)]
    public ?bool $isPrimary = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    /** @var int[]|null */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?array $websiteIds = null;

    /** @var int[]|null */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?array $customerGroupIds = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?int $sortOrder = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?bool $stopRulesProcessing = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?float $discountQty = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?int $discountStep = null;

    /** 0 = no, 1 = free shipping for matching items, 2 = free shipping for the whole shipment */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?int $simpleFreeShipping = null;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?bool $applyToShipping = null;

    /** @var bool|null Used in validate response */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?bool $isValid = null;

    /** @var float|null Discount preview amount from validate */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?float $discountPreview = null;

    /** @var string|null Validation message */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $validationMessage = null;

    public static function afterLoad(self $dto, object $model): void
    {
        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->load($model->getRuleId());

        $dto->ruleName = $rule->getName();
        $dto->discountType = self::DISCOUNT_TYPE_MAP[$rule->getSimpleAction()] ?? $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();
        $dto->description = $rule->getDescription();
        $dto->isActive = (bool) $rule->getIsActive();
        $dto->usageLimit = $rule->getUsesPerCoupon() ? (int) $rule->getUsesPerCoupon() : null;
        $dto->usagePerCustomer = $rule->getUsesPerCustomer() ? (int) $rule->getUsesPerCustomer() : null;
        $dto->fromDate = $rule->getFromDate();
        $dto->toDate = $rule->getToDate();
        $dto->websiteIds = array_map('intval', (array) $rule->getWebsiteIds());
        $dto->customerGroupIds = array_map('intval', (array) $rule->getCustomerGroupIds());
        $dto->sortOrder = (int) $rule->getSortOrder();
        $dto->stopRulesProcessing = (bool) $rule->getStopRulesProcessing();
        $dto->discountQty = $rule->getDiscountQty() !== null ? (float) $rule->getDiscountQty() : null;
        $dto->discountStep = (int) $rule->getDiscountStep();
        $dto->simpleFreeShipping = (int) $rule->getSimpleFreeShipping();
        $dto->applyToShipping = (bool) $rule->getApplyToShipping();

        $conditions = $rule->getConditions();
        if ($conditions) {
            $dto->minimumSubtotal = self::extractMinimumSubtotal($conditions);
        }
    }

    private static function extractMinimumSubtotal(\Mage_Rule_Model_Condition_Abstract $conditions): ?float
    {
        foreach ($conditions->getConditions() as $condition) {
            if ($condition->getAttribute() === 'base_subtotal' && $condition->getOperator() === '>=') {
                return (float) $condition->getValue();
            }
        }
        return null;
    }
}
