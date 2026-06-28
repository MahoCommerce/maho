<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CustomerGroupProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var CustomerGroup $data */
        $code = trim($data->code);

        // Code is required on create; on update an omitted code leaves the existing value untouched.
        if ($isNew && $code === '') {
            throw new BadRequestHttpException('Customer group code is required.');
        }

        if (mb_strlen($code) > \Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH) {
            throw new BadRequestHttpException(
                sprintf(
                    'Customer group code must not exceed %d characters.',
                    \Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH,
                ),
            );
        }
    }
}
