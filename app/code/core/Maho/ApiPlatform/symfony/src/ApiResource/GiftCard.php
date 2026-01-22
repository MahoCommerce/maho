<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Query;
use Maho\ApiPlatform\State\Provider\GiftCardProvider;

#[ApiResource(
    shortName: 'GiftCard',
    description: 'Gift Card resource',
    provider: GiftCardProvider::class,
    operations: [
        new Get(
            uriTemplate: '/giftcards/{code}',
            description: 'Get a gift card by code'
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'checkGiftcardBalance',
            args: ['code' => ['type' => 'String!']],
            description: 'Check gift card balance by code'
        ),
    ]
)]
class GiftCard
{
    public ?int $id = null;
    public ?string $code = null;
    public float $balance = 0.0;
    public float $initialBalance = 0.0;
    public ?string $status = null;
    public ?string $expirationDate = null;
    public ?string $currencyCode = null;
    public ?string $createdAt = null;
}
