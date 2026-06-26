<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    security: 'true',
    mahoLabel: 'Stores & Store Views',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Manage'],
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
class Store extends CrudResource
{
    public const MODEL = 'core/store';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public string $code = '';

    #[ApiProperty(writable: false)]
    public string $name = '';

    #[ApiProperty(writable: false)]
    public ?int $websiteId = null;

    #[ApiProperty(writable: false)]
    public ?int $groupId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $groupName = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?int $rootCategoryId = null;

    #[ApiProperty(writable: false)]
    public bool $isActive = true;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $baseUrl = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $baseLinkUrl = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $baseMediaUrl = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $locale = null;

    /** @var array{base: string, default: string}|null */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?array $currency = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?bool $success = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $storeGroup = \Mage::app()->getGroup($model->getGroupId());
        $dto->groupName = $storeGroup ? $storeGroup->getName() : null;
        $dto->rootCategoryId = (int) $model->getRootCategoryId();
        $dto->baseUrl = $model->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
        $dto->baseLinkUrl = $model->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_LINK);
        $dto->baseMediaUrl = $model->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $dto->locale = \Mage::getStoreConfig('general/locale/code', $model);
        $dto->currency = [
            'base' => $model->getBaseCurrencyCode(),
            'default' => $model->getDefaultCurrencyCode(),
        ];
    }
}
