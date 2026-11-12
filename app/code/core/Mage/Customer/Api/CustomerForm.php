<?php

/**
 * The custom fields of a storefront customer or address form.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Query;
use Maho\ApiPlatform\Metadata\EnumSource;
use Maho\Config\ApiResource;

#[ApiResource(
    security: 'true',
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View'],
    shortName: 'CustomerForm',
    description: 'The custom fields that a storefront form shows to the customer group of the caller on the website of the store, '
        . 'so a headless storefront can show them and send their values in customAttributes',
    provider: CustomerFormProvider::class,
    operations: [
        new Get(
            uriTemplate: '/customer-forms/{id}',
            requirements: ['id' => '[a-z_]+'],
            security: 'true',
            description: 'Get the custom fields of a storefront form. The id is customer_account_create (registration), '
                . 'customer_account_edit (account), checkout_register (checkout registration), customer_address_edit '
                . '(address book and checkout) or customer_register_address (address of the registration). A customer token '
                . 'gets the fields of its customer group. A guest gets the fields of the default group of the store, and of '
                . 'the NOT LOGGED IN group for customer_address_edit, as in the guest checkout',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get the custom fields of a storefront form', security: 'true'),
    ],
)]
class CustomerForm extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true, writable: false, description: 'Form code', extraProperties: [EnumSource::KEY => ['customer_account_create', 'customer_account_edit', 'checkout_register', 'customer_address_edit', 'customer_register_address']])]
    public string $id = '';

    #[ApiProperty(writable: false, description: 'Entity type of the fields: customer or customer_address')]
    public string $entityType = '';

    /**
     * @var list<array{title: ?string, fields: list<array<string, mixed>>}>
     */
    #[ApiProperty(writable: false, description: 'The fields by group of the attribute set of the customer group. The first group has no title: its fields '
        . 'join the fields of the platform. Each other group has its title in the store view. A field has code, label, input, '
        . 'isRequired, sortOrder, options (value and label, for a dropdown or a multiselect), multilineCount, validateRules and dependsOn. '
        . 'dependsOn is null, or the condition that shows the field, in the format of dependsOn of /custom-attributes. A field that it hides is not required')]
    public array $groups = [];
}
