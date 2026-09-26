<?php

/**
 * The global search of the admin: orders, customers, products and the other search sources of the configuration.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

namespace Mage\Adminhtml\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoId: 'global-search',
    mahoLabel: 'Global search',
    mahoSection: 'System',
    mahoOperations: ['read' => 'Search'],
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('global-search/read')",
    shortName: 'GlobalSearch',
    description: 'Global search of the admin',
    provider: GlobalSearchProvider::class,
    operations: [
        new Get(
            uriTemplate: '/global-search',
            security: "is_granted('ROLE_ADMIN') or is_granted('global-search/read')",
            description: 'Search with the search sources of the admin global search (configuration node adminhtml/global_search). '
                . 'Query: query (at least 2 characters, a shorter query gives no items), limit (1 to 25, default 10, per source). '
                . 'The configuration admin/global_search/enable must be on. An admin token gets the sources that its admin role allows. '
                . 'An API user gets the orders with orders access, the customers with customers access and the products with products access, and no other sources. '
                . 'A token with a store restriction gets only the orders of its stores, the customers of the websites of its stores and the products, and no other sources. '
                . 'Response: query and items: source, type, entity, entityId, name, description.',
        ),
    ],
    graphQlOperations: [],
)]
class GlobalSearch extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'global_search';

    #[ApiProperty(identifier: true, description: 'Search text')]
    public string $query = '';
}
