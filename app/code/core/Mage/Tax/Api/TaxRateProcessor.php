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

final class TaxRateProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var TaxRate $data */

        // Code and country are required on create; on update an omitted value
        // leaves the existing one untouched.
        if ($isNew) {
            if (trim($data->code) === '') {
                throw new BadRequestHttpException('Tax rate code is required.');
            }
            if (trim($data->taxCountryId) === '') {
                throw new BadRequestHttpException('Tax country is required.');
            }
        }

        if (!is_numeric($data->rate) || $data->rate < 0) {
            throw new BadRequestHttpException('Rate must be a number greater than or equal to zero.');
        }
    }
}
