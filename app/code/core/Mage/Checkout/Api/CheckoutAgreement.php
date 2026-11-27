<?php

/**
 * A terms and conditions text that a customer accepts in the checkout or in the registration form.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use Maho\ApiPlatform\Metadata\ListFilter;
use Maho\Config\ApiResource;
use Mage_Checkout_Model_Agreement as Agreement;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: 'true',
    mahoLabel: 'Terms and Conditions',
    mahoSection: 'Sales',
    mahoOperations: ['read' => 'View'],
    mahoCustomerScoped: true,
    shortName: 'CheckoutAgreement',
    description: 'The terms and conditions of the store. The checkout sends the IDs of the accepted ones as agreementIds, and so does a registration',
    provider: CheckoutAgreementProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/checkout-agreements',
            security: 'true',
            description: 'List the active terms and conditions of the store, in their order. Empty when the store does not ask for them',
            parameters: [
                'location' => new ListFilter('Where the terms show. Default: checkout', enum: [Agreement::LOCATION_CHECKOUT, Agreement::LOCATION_REGISTRATION]),
            ],
            paginationEnabled: false,
        ),
        new GetCollection(
            uriTemplate: '/customers/me/checkout-agreements/pending',
            name: 'my_pending_checkout_agreements',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'List the registration terms that the logged-in customer did not accept, or must accept again because their text changed. Accept them with POST /customers/me/agreement-acceptances',
            paginationEnabled: false,
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: Agreement::class)]
class CheckoutAgreement extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'sales/checkoutagreement';

    #[ApiProperty(identifier: true, writable: false, example: 1)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(writable: false, example: 'Terms of Sale')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $name = null;

    #[ApiProperty(writable: false, description: 'The text to show. HTML when isHtml is true, plain text otherwise')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $content = null;

    #[ApiProperty(writable: false, description: 'The label of the checkbox. HTML when isHtml is true', example: 'I accept the terms of sale')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $checkboxText = null;

    #[ApiProperty(writable: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?bool $isHtml = null;

    #[ApiProperty(writable: false, description: 'The CSS height of the box of the text', example: '200px')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $contentHeight = null;

    #[ApiProperty(writable: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $position = null;

    #[ApiProperty(writable: false, description: 'The number of the current text. A change of the text makes a new version', example: 2)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $version = null;
}
