<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TaxClassProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var TaxClass $data */

        // Name is required on create; on update an omitted name leaves the existing value untouched.
        if ($isNew && trim($data->className) === '') {
            throw new BadRequestHttpException('Tax class name is required.');
        }

        $allowed = [
            \Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT,
            \Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER,
        ];
        if (!in_array($data->classType, $allowed, true)) {
            throw new BadRequestHttpException('Tax class type must be PRODUCT or CUSTOMER.');
        }
    }
}
