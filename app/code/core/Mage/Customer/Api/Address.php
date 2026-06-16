<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Manage'],
    mahoCustomerScoped: true,
    shortName: 'Address',
    description: 'View, create, update, and delete saved addresses',
    provider: AddressProvider::class,
    processor: AddressProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/addresses/{id}',
            description: 'Get a specific address by ID',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Put(
            uriTemplate: '/addresses/{id}',
            description: 'Update an address',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Delete(
            uriTemplate: '/addresses/{id}',
            description: 'Delete an address',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new GetCollection(
            name: 'get_my_addresses',
            uriTemplate: '/addresses',
            description: 'List all addresses for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Post(
            name: 'create_my_address',
            uriTemplate: '/addresses',
            description: 'Create a new address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new GetCollection(
            name: 'get_me_addresses',
            uriTemplate: '/customers/me/addresses',
            description: 'List all addresses for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Post(
            name: 'create_me_address',
            uriTemplate: '/customers/me/addresses',
            description: 'Create a new address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Get(
            name: 'get_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Get an address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Put(
            name: 'update_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Update an address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Delete(
            name: 'delete_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Delete an address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new GetCollection(
            name: 'get_customer_addresses',
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => new Link(toProperty: 'customerId'),
            ],
            description: 'List all addresses for a customer',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Post(
            name: 'create_customer_address',
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => new Link(toProperty: 'customerId'),
            ],
            description: 'Create a new address for a customer',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get an address by ID', security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')"),
        new QueryCollection(name: 'collection_query', description: 'Get addresses', security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')"),
        new QueryCollection(
            name: 'myAddresses',
            args: [],
            description: 'Get all addresses for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Query(
            name: 'address',
            args: ['id' => ['type' => 'ID!']],
            description: 'Get a single address by ID',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'createAddress',
            args: [
                'firstName' => ['type' => 'String!'],
                'lastName' => ['type' => 'String!'],
                'street' => ['type' => '[String!]!'],
                'city' => ['type' => 'String!'],
                'region' => ['type' => 'String'],
                'regionId' => ['type' => 'Int'],
                'postcode' => ['type' => 'String!'],
                'countryId' => ['type' => 'String!'],
                'telephone' => ['type' => 'String!'],
                'company' => ['type' => 'String'],
                'isDefaultBilling' => ['type' => 'Boolean'],
                'isDefaultShipping' => ['type' => 'Boolean'],
            ],
            description: 'Create a new address for the authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'updateAddress',
            args: [
                'id' => ['type' => 'ID!'],
                'firstName' => ['type' => 'String'],
                'lastName' => ['type' => 'String'],
                'street' => ['type' => '[String!]'],
                'city' => ['type' => 'String'],
                'region' => ['type' => 'String'],
                'regionId' => ['type' => 'Int'],
                'postcode' => ['type' => 'String'],
                'countryId' => ['type' => 'String'],
                'telephone' => ['type' => 'String'],
                'company' => ['type' => 'String'],
                'isDefaultBilling' => ['type' => 'Boolean'],
                'isDefaultShipping' => ['type' => 'Boolean'],
            ],
            description: 'Update an existing address',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new DeleteMutation(
            name: 'deleteAddress',
            args: ['id' => ['type' => 'ID!']],
            description: 'Delete an address',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class Address extends CrudResource
{
    public const MODEL = 'customer/address';

    /** Admin ACL gate. Customer addresses are gated by the same node as customer mgmt. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_CustomerController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[ApiProperty(identifier: false, extraProperties: ['modelField' => 'parent_id'])]
    public ?int $customerId = null;

    public string $firstname = '';

    public string $lastname = '';

    public ?string $company = null;

    /** @var string[]|string Street lines (accepts string or array, normalized to array in processor) */
    public mixed $street = [];

    public string $city = '';
    public ?string $region = null;

    /**
     * Region ID. The native type is `mixed` so API Platform's serializer
     * accepts BOTH int (e.g. from the backend itself) and string (which is
     * what HTML <select> elements / JSON-encoded form values normally send).
     * The processor normalises to int before persisting. Don't narrow the
     * docblock to `int|null`, the serializer reads that and rejects the
     * stringly-encoded form values.
     *
     * @var int|string|null
     */
    public mixed $regionId = null;

    public string $postcode = '';

    public string $countryId = '';

    public string $telephone = '';

    // `writable: true` because AddressProcessor::process() reads these on
    // create/update to flip the customer's default_billing / default_shipping
    // pointers, declaring the property writable: false silently dropped the
    // input value, leaving the flag at its default `false` and the
    // processor's "if ($data->isDefaultBilling)" branch unreachable.
    // The `computed` extra still indicates that on READ the value is derived
    // from the customer's default_billing column, not a column on the
    // address itself.
    #[ApiProperty(extraProperties: ['computed' => true])]
    public bool $isDefaultBilling = false;

    #[ApiProperty(extraProperties: ['computed' => true])]
    public bool $isDefaultShipping = false;

    public static function afterLoad(self $dto, object $model): void
    {
        // Normalize street to array
        $street = $model->getStreet();
        $dto->street = is_array($street) ? $street : ($street ? [$street] : []);

        // Normalize regionId
        $regionId = $model->getData('region_id');
        $dto->regionId = $regionId ? (int) $regionId : null;
    }

    /**
     * Create Address DTO from an order address model.
     */
    public static function fromOrderAddress(\Mage_Sales_Model_Order_Address $address): self
    {
        return self::fromGenericAddress($address);
    }

    /**
     * Create Address DTO from a quote address model.
     */
    public static function fromQuoteAddress(\Mage_Sales_Model_Quote_Address $address): self
    {
        return self::fromGenericAddress($address);
    }

    /**
     * Create Address DTO from a customer address model with default billing/shipping info.
     */
    public static function fromCustomerAddress(
        \Mage_Customer_Model_Address $address,
        ?\Mage_Customer_Model_Customer $customer = null,
    ): self {
        $dto = self::fromModel($address);

        if ($customer !== null) {
            $dto->isDefaultBilling = $address->getId() == $customer->getDefaultBilling();
            $dto->isDefaultShipping = $address->getId() == $customer->getDefaultShipping();
        }

        return $dto;
    }

    /**
     * Map common address fields from any address-like model (order, quote).
     */
    private static function fromGenericAddress(\Maho\DataObject $address): self
    {
        $dto = new self();
        $dto->id = (int) $address->getId();
        $dto->firstname = $address->getData('firstname') ?? '';
        $dto->lastname = $address->getData('lastname') ?? '';
        $dto->company = $address->getData('company');
        $dto->street = $address->getStreet();
        $dto->city = $address->getData('city') ?? '';
        $dto->region = $address->getData('region');
        $dto->regionId = $address->getData('region_id') ? (int) $address->getData('region_id') : null;
        $dto->postcode = $address->getData('postcode') ?? '';
        $dto->countryId = $address->getData('country_id') ?? '';
        $dto->telephone = $address->getData('telephone') ?? '';

        return $dto;
    }
}
