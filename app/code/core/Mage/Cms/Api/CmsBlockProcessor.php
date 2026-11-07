<?php

/**
 * Give a CMS block write to the CMS block service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;

final class CmsBlockProcessor extends CrudProcessor
{
    #[\Override]
    protected function loadOrFail(string $modelAlias, int|string $id, string $notFoundMessage): \Mage_Cms_Model_Block
    {
        return $this->service()->getById((int) $id);
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        // An omitted field is null on the DTO, so that an update leaves it alone. A new block is enabled, for every store view
        if (!$model->getId()) {
            if ($model->getData('is_active') === null) {
                $model->setData('is_active', 1);
            }
            if ($model->getData('stores') === null) {
                $model->setData('stores', [0]);
            }
        }
    }

    #[\Override]
    protected function safeSave(\Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        /** @var \Mage_Cms_Model_Block $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function safeDelete(\Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        /** @var \Mage_Cms_Model_Block $model */
        $this->service()->delete($model);
    }

    private function service(): \Mage_Cms_Service_Block
    {
        /** @var \Mage_Cms_Service_Block */
        return \Mage::getService('cms/block');
    }
}
