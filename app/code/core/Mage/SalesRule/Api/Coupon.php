<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
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

#[ApiResource(
    shortName: 'Coupon',
    description: 'Coupon / price rule management resource',
    provider: CouponProvider::class,
    processor: CouponProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get a coupon by ID',
        ),
        new GetCollection(
            uriTemplate: '/coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Get all coupons',
        ),
        new Post(
            uriTemplate: '/coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Create a new coupon with price rule',
        ),
        new Put(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
            description: 'Update a coupon and its price rule',
        ),
        new Delete(
            uriTemplate: '/coupons/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
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
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get all coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'createCoupon',
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
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'updateCoupon',
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
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new DeleteMutation(
            name: 'deleteCoupon',
            description: 'Delete a coupon and its price rule',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'validateCoupon',
            description: 'Validate a coupon code',
            args: [
                'code' => ['type' => 'String!'],
                'cartId' => ['type' => 'Int'],
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER') or is_granted('ROLE_CUSTOMER')",
        ),
    ],
    extraProperties: [
        'model' => 'salesrule/coupon',
    ],
)]
class Coupon extends CrudResource
{
    private const DISCOUNT_TYPE_MAP = [
        'by_percent' => 'percent',
        'by_fixed' => 'fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $code = null;

    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'rule_id'])]
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
