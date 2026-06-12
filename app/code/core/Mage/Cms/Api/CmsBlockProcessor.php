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
        $content = $model->getData('content');
        if ($content !== null) {
            $model->setData('content', \Mage::getSingleton('core/input_filter_maliciousCode')->filter($content));
        }
    }
}
