<?php

/**
 * The proof that a customer accepted one version of a terms and conditions text.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Maho\Config\ApiResource;
use Mage_Checkout_Model_Agreement_Acceptance as Acceptance;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: "is_granted('ROLE_CUSTOMER')",
    mahoLabel: 'Accepted Terms and Conditions',
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Accept'],
    mahoCustomerScoped: true,
    shortName: 'AgreementAcceptance',
    description: 'The terms and conditions that the logged-in customer accepted, with the version and the date',
    provider: AgreementAcceptanceProvider::class,
    processor: AgreementAcceptanceProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customers/me/agreement-acceptances',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'List the terms and conditions that the logged-in customer accepted, newest first',
        ),
        new Post(
            uriTemplate: '/customers/me/agreement-acceptances',
            name: 'accept_checkout_agreement',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Accept a registration terms text that waits for the acceptance of the logged-in customer. GET /customers/me/checkout-agreements/pending lists them',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['agreementId' => ['type' => 'integer']],
                'required' => ['agreementId'],
            ]]]))),
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: Acceptance::class)]
class AgreementAcceptance extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true, writable: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $agreementId = null;

    #[ApiProperty(writable: false, description: 'The name of the terms when the customer accepted them')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $agreementName = null;

    #[ApiProperty(writable: false, description: 'The version of the text that the customer accepted')]
    #[Map(if: new TargetClass(self::class))]
    public ?int $agreementVersion = null;

    #[ApiProperty(writable: false, description: 'Where the customer accepted: checkout, registration or account', example: 'checkout')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $context = null;

    #[ApiProperty(writable: false, description: 'The order of a checkout acceptance')]
    #[Map(if: new TargetClass(self::class))]
    public ?int $orderId = null;

    #[ApiProperty(writable: false, description: 'In UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $createdAt = null;
}
