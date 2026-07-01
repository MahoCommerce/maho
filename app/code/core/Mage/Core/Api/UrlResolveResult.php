<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    security: 'true',
    mahoId: 'url-resolver',
    mahoLabel: 'URL Resolver',
    mahoSection: 'System',
    mahoOperations: ['read' => 'Resolve'],
    shortName: 'UrlResolveResult',
    description: 'URL resolution result - maps URLs to their targets',
    provider: UrlResolverProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/url-resolver',
            security: 'true',
            description: 'Resolve a URL path to its target (use ?path=your-url)',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a URL resolve result', security: 'true'),
        new QueryCollection(name: 'collection_query', description: 'Get URL resolve results', security: 'true'),
        new Query(
            name: 'resolveUrl',
            args: ['path' => ['type' => 'String!']],
            description: 'Resolve a URL path to its target',
            resolver: CustomQueryResolver::class,
        ),
    ],
)]
class UrlResolveResult extends \Maho\ApiPlatform\Resource
{
    /** @var string The resolved entity type: 'cms_page', 'category', 'product', or 'not_found' */
    public string $type = 'not_found';

    /** @var int|null The entity ID */
    public ?int $id = null;

    /** @var string|null The identifier/URL key */
    public ?string $identifier = null;

    /** @var string|null Redirect URL if this is a redirect */
    public ?string $redirectUrl = null;

    /** @var int Redirect type (301, 302) or 0 if not a redirect */
    public int $redirectType = 0;
}
