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
use Maho\ApiPlatform\CrudResource;

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
    extraProperties: [
        'model' => 'core/store',
    ],
)]
class Store extends CrudResource
{
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

    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'is_active'])]
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
