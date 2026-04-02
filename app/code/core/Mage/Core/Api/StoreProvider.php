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

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

class StoreProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $stores = [];

        foreach (\Mage::app()->getStores(false) as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            $storeGroup = \Mage::app()->getGroup($store->getGroupId());

            $dto = new Store();
            $dto->id = (int) $store->getId();
            $dto->code = $store->getCode();
            $dto->name = $store->getName();
            $dto->websiteId = (int) $store->getWebsiteId();
            $dto->groupId = (int) $store->getGroupId();
            $dto->groupName = $storeGroup ? $storeGroup->getName() : null;
            $dto->rootCategoryId = (int) $store->getRootCategoryId();
            $dto->isActive = (bool) $store->getIsActive();
            $dto->baseUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
            $dto->baseLinkUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_LINK);
            $dto->baseMediaUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA);
            $dto->locale = \Mage::getStoreConfig('general/locale/code', $store);
            $dto->currency = [
                'base' => $store->getBaseCurrencyCode(),
                'default' => $store->getDefaultCurrencyCode(),
            ];
            $stores[] = $dto;
        }

        return $stores;
    }
}
