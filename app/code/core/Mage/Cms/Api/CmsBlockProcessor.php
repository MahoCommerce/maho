<?php

/**
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
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        // On create, apply defaults for fields omitted from the request. They are nullable on
        // the DTO so that an omitted field on a partial update is a no-op (no silent reset);
        // create still needs sane defaults: enabled block assigned to all store views.
        if (!$model->getId()) {
            if ($model->getData('is_active') === null) {
                $model->setData('is_active', 1);
            }
            if ($model->getData('stores') === null) {
                $model->setData('stores', [0]);
            }
        }

        // Content sanitization lives in Mage_Cms_Model_Resource_Block::_beforeSave(), so it covers
        // every save path. Filtering here as well would run over unresolved template directives
        // and mangle them ({{media url="..."}} becomes a broken %7B%7B… URL).
    }
}
