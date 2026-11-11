<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

namespace Mage\ProductAlert\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Metadata\EnumSource;
use Maho\Config\ApiResource;

#[ApiResource(
    security: "is_granted('ROLE_CUSTOMER')",
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Subscribe/Unsubscribe'],
    mahoCustomerScoped: true,
    shortName: 'ProductAlert',
    description: 'Price alerts and stock alerts of the customer',
    provider: ProductAlertProvider::class,
    processor: ProductAlertProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customers/me/product-alerts',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Get the price alerts and the stock alerts of the current customer',
        ),
        new Post(
            uriTemplate: '/customers/me/product-alerts',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Subscribe the current customer to the price alert or the stock alert of a product. Body: type (price or stock), productId',
        ),
        new Delete(
            uriTemplate: '/customers/me/product-alerts/{id}',
            requirements: ['id' => '(price|stock)-\d+'],
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Unsubscribe the current customer from an alert. The ID is the type, a hyphen and the product ID, for example price-42',
        ),
    ],
    graphQlOperations: [
        // The provider reads only the alerts of the customer of the token, so the owner is always this customer.
        new Query(name: 'item_query', description: 'Get a product alert of the current customer by ID', security: "is_owner(object, 'customerId')"),
        new QueryCollection(name: 'collection_query', description: 'Get the product alerts of the current customer', security: "is_granted('ROLE_CUSTOMER')"),
        new QueryCollection(name: 'my', description: 'Get the product alerts of the current customer', security: "is_granted('ROLE_CUSTOMER')"),
        new Mutation(
            name: 'subscribe',
            description: 'Subscribe the current customer to the price alert or the stock alert of a product',
            args: [
                'type' => ['type' => 'String!', 'description' => 'price or stock'],
                'productId' => ['type' => 'Int!', 'description' => 'Product ID'],
            ],
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Mutation(
            name: 'unsubscribe',
            description: 'Unsubscribe the current customer from the price alert or the stock alert of a product',
            args: [
                'type' => ['type' => 'String!', 'description' => 'price or stock'],
                'productId' => ['type' => 'Int!', 'description' => 'Product ID'],
            ],
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
class ProductAlert extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true, writable: false, description: 'The type, a hyphen and the product ID, for example price-42')]
    public ?string $id = null;

    // Never serialized; exists for the is_owner(object, 'customerId') security expression.
    #[ApiProperty(readable: false, writable: false)]
    public ?int $customerId = null;

    #[ApiProperty(description: 'The event that the alert waits for', extraProperties: [EnumSource::KEY => ['price', 'stock']])]
    public ?string $type = null;

    public ?int $productId = null;

    #[ApiProperty(writable: false, description: 'Price alert only: the price of the product at the time of the subscription')]
    public ?float $price = null;

    #[ApiProperty(writable: false)]
    public ?string $addedAt = null;

    public static function fromModel(\Mage_ProductAlert_Model_Type $type, \Mage_ProductAlert_Model_Price|\Mage_ProductAlert_Model_Stock $alert): self
    {
        $dto = new self();
        $dto->id = $type->value . '-' . $alert->getProductId();
        $dto->customerId = $alert->getCustomerId();
        $dto->type = $type->value;
        $dto->productId = $alert->getProductId();
        $dto->price = $alert instanceof \Mage_ProductAlert_Model_Price ? $alert->getPrice() : null;
        $dto->addedAt = $alert->getAddDate();
        return $dto;
    }
}
