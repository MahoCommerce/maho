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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CmsPageProcessor extends CrudProcessor
{
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        // On create, apply defaults for fields omitted from the request. They are nullable on
        // the DTO so that an omitted field on a partial update is a no-op (no silent reset);
        // create still needs sane defaults: enabled page assigned to all store views.
        if (!$model->getId()) {
            // A page needs a URL identifier; reject a create that omits it with a
            // 4xx rather than persisting a page with an empty identifier.
            if (trim((string) $model->getData('identifier')) === '') {
                throw new BadRequestHttpException('Identifier is required.');
            }
            if ($model->getData('is_active') === null) {
                $model->setData('is_active', 1);
            }
            if ($model->getData('stores') === null) {
                $model->setData('stores', [0]);
            }
        }

        // Content sanitization lives in Mage_Cms_Model_Resource_Page::_beforeSave(), so it covers
        // every save path. Filtering here as well would run over unresolved template directives
        // and mangle them ({{media url="..."}} becomes a broken %7B%7B… URL).
    }
}
