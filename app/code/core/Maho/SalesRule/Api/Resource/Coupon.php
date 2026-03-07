<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SalesRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\SalesRule\Api\Resource;

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
use Maho\SalesRule\Api\State\Provider\CouponProvider;
use Maho\SalesRule\Api\State\Processor\CouponProcessor;

#[ApiResource(
    shortName: 'Coupon',
    description: 'Coupon / price rule management resource',
    provider: CouponProvider::class,
    processor: CouponProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/coupons/{id}',
            description: 'Get a coupon by ID',
        ),
        new GetCollection(
            uriTemplate: '/coupons',
            description: 'Get all coupons',
        ),
        new Post(
            uriTemplate: '/coupons',
            description: 'Create a new coupon with price rule',
        ),
        new Put(
            uriTemplate: '/coupons/{id}',
            description: 'Update a coupon and its price rule',
        ),
        new Delete(
            uriTemplate: '/coupons/{id}',
            description: 'Delete a coupon and its price rule',
        ),
        new Post(
            uriTemplate: '/coupons/validate',
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
)]
class Coupon
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    public ?string $code = null;

    #[ApiProperty(writable: false)]
    public ?int $ruleId = null;

    #[ApiProperty(writable: false)]
    public ?string $ruleName = null;

    /** percent, fixed, cart_fixed, buy_x_get_y */
    public ?string $discountType = null;

    public ?float $discountAmount = null;

    public ?string $description = null;

    public bool $isActive = true;

    public ?int $usageLimit = null;

    public ?int $usagePerCustomer = null;

    #[ApiProperty(writable: false)]
    public int $timesUsed = 0;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?float $minimumSubtotal = null;

    /** @var bool|null Used in validate response */
    #[ApiProperty(writable: false)]
    public ?bool $isValid = null;

    /** @var float|null Discount preview amount from validate */
    #[ApiProperty(writable: false)]
    public ?float $discountPreview = null;

    /** @var string|null Validation message */
    #[ApiProperty(writable: false)]
    public ?string $validationMessage = null;
}
