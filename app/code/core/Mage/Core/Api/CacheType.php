<?php

/**
 * One cache type of System > Cache Management.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Cache Management',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Enable, disable, refresh and flush'],
    security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/read')",
    shortName: 'CacheType',
    description: 'Cache type',
    provider: CacheTypeProvider::class,
    processor: CacheTypeProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/cache-types',
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/read')",
            description: 'List the cache types of System > Cache Management with their state: enabled (the type is in use) and invalidated (the stored data is out of date and the type needs a refresh).',
        ),
        new Get(
            uriTemplate: '/cache-types/{code}',
            requirements: ['code' => '[a-z0-9_]+'],
            uriVariables: ['code' => new Link(fromClass: self::class, identifiers: ['code'])],
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/read')",
            description: 'Get one cache type by its code, for example config, layout, block_html, full_page, translate, collections or eav.',
        ),
        new Put(
            uriTemplate: '/cache-types/{code}',
            requirements: ['code' => '[a-z0-9_]+'],
            uriVariables: ['code' => new Link(fromClass: self::class, identifiers: ['code'])],
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/write')",
            description: 'Enable or disable one cache type. Body: enabled (boolean). A disabled type is also cleaned. Response: the cache type after the change.',
        ),
        new Post(
            uriTemplate: '/cache-types/{code}/refresh',
            requirements: ['code' => '[a-z0-9_]+'],
            uriVariables: ['code' => new Link(fromClass: self::class, identifiers: ['code'])],
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/write')",
            description: 'Refresh one cache type: remove its stored data and clear its invalidated state. The data is built again on the next request. Response: the cache type after the refresh.',
        ),
        new Post(
            uriTemplate: '/cache-types/flush-all',
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/write')",
            description: 'Flush the whole cache storage: remove the stored data of every cache type at once, like the Flush Cache Storage button. Response: success (true) and flushed (the number of cache types).',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a cache type by its code',
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List the cache types',
            security: "is_granted('ROLE_ADMIN') or is_granted('cache-types/read')",
        ),
    ],
)]
class CacheType extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_CacheController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_CacheController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false, description: 'Cache type code, for example config or block_html')]
    public string $code = '';

    #[ApiProperty(writable: false, description: 'Name of the cache type')]
    public string $label = '';

    #[ApiProperty(writable: false, description: 'What the cache type stores')]
    public ?string $description = null;

    #[ApiProperty(description: 'True when the cache type is in use')]
    public bool $enabled = false;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'True when the stored data is out of date and the type needs a refresh')]
    public bool $invalidated = false;

    #[ApiProperty(writable: false, description: 'Cache tags of the type, comma-separated')]
    public ?string $tags = null;
}
