<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Customer\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
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
use Maho\Customer\Api\State\Provider\AddressProvider;
use Maho\Customer\Api\State\Processor\AddressProcessor;

#[ApiResource(
    shortName: 'Address',
    description: 'Customer address resource',
    provider: AddressProvider::class,
    processor: AddressProcessor::class,
    operations: [
        // Simple routes using only address ID (globally unique)
        new Get(
            uriTemplate: '/addresses/{id}',
            description: 'Get a specific address by ID',
        ),
        new Put(
            uriTemplate: '/addresses/{id}',
            description: 'Update an address',
        ),
        new Delete(
            uriTemplate: '/addresses/{id}',
            description: 'Delete an address',
        ),
        // Simple route for authenticated customers to create/list their own addresses
        new GetCollection(
            name: 'get_my_addresses',
            uriTemplate: '/addresses',
            description: 'List all addresses for the authenticated customer',
        ),
        new Post(
            name: 'create_my_address',
            uriTemplate: '/addresses',
            description: 'Create a new address for the authenticated customer',
        ),
        // Routes using /customers/me/* pattern (frontend compatibility)
        new GetCollection(
            name: 'get_me_addresses',
            uriTemplate: '/customers/me/addresses',
            description: 'List all addresses for the authenticated customer',
        ),
        new Post(
            name: 'create_me_address',
            uriTemplate: '/customers/me/addresses',
            description: 'Create a new address for the authenticated customer',
        ),
        new Get(
            name: 'get_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Get an address for the authenticated customer',
        ),
        new Put(
            name: 'update_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Update an address for the authenticated customer',
        ),
        new Delete(
            name: 'delete_me_address',
            uriTemplate: '/customers/me/addresses/{id}',
            description: 'Delete an address for the authenticated customer',
        ),
        // Customer-scoped routes for listing/creating (admin or explicit customer ID)
        new GetCollection(
            name: 'get_customer_addresses',
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => new Link(toProperty: 'customerId'),
            ],
            description: 'List all addresses for a customer',
        ),
        new Post(
            name: 'create_customer_address',
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => new Link(toProperty: 'customerId'),
            ],
            description: 'Create a new address for a customer',
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
class Address
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[ApiProperty(identifier: false)]
    public ?int $customerId = null;
    public string $firstName = '';
    public string $lastName = '';
    public ?string $company = null;
    /** @var string[]|string Street lines (accepts string or array, normalized to array in processor) */
    public mixed $street = [];
    public string $city = '';
    public ?string $region = null;
    /** @var int|null Region ID (accepts string from frontend, normalized to int in processor) */
    public mixed $regionId = null;
    public string $postcode = '';
    public string $countryId = '';
    public string $telephone = '';
    public bool $isDefaultBilling = false;
    public bool $isDefaultShipping = false;
}
