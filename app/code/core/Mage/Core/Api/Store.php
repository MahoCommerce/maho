<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'Store',
    description: 'Store and website listing',
    operations: [
        new GetCollection(
            uriTemplate: '/stores',
            name: 'list_stores',
            provider: StoreProvider::class,
            security: 'true',
            description: 'List all active stores and websites',
        ),
        new Post(
            uriTemplate: '/stores/switch/{storeCode}',
            name: 'switch_store',
            processor: StoreProcessor::class,
            security: 'true',
            description: 'Switch current store context',
        ),
    ],
)]
class Store extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    public string $code = '';
    public string $name = '';
    public ?int $websiteId = null;
    public ?int $groupId = null;
    public ?string $groupName = null;
    public ?int $rootCategoryId = null;
    public bool $isActive = true;
    public ?string $baseUrl = null;
    public ?string $baseLinkUrl = null;
    public ?string $baseMediaUrl = null;
    public ?string $locale = null;

    /** @var array{base: string, default: string}|null */
    public ?array $currency = null;

    public ?bool $success = null;
}
