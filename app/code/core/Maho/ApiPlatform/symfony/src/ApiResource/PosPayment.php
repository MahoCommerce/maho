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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\State\Provider\PaymentProvider;
use Maho\ApiPlatform\State\Processor\PaymentProcessor;

#[ApiResource(
    shortName: 'PosPayment',
    description: 'Payment resource for POS and headless checkout',
    provider: PaymentProvider::class,
    processor: PaymentProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/pos-payments/{id}',
            description: 'Get a POS payment by ID'
        ),
        new GetCollection(
            uriTemplate: '/pos-payments',
            description: 'Get POS payments collection'
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'posPayment',
            args: ['id' => ['type' => 'ID!']],
            description: 'Get POS payment by ID'
        ),
        new QueryCollection(
            name: 'orderPosPayments',
            args: ['orderId' => ['type' => 'ID!']],
            description: 'Get all POS payments for an order'
        ),
    ]
)]
class PosPayment
{
    public ?int $id = null;
    public ?int $orderId = null;
    public ?int $registerId = null;
    public ?string $methodCode = null;
    public ?string $methodLabel = null;
    public float $amount = 0.0;
    public float $baseAmount = 0.0;
    public ?string $currencyCode = null;
    public ?string $terminalId = null;
    public ?string $transactionId = null;
    public ?string $cardType = null;
    public ?string $cardLast4 = null;
    public ?string $authCode = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    /** @var array<string, mixed> */
    public array $receiptData = [];
}
