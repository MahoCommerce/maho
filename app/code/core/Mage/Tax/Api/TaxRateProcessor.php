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
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TaxRateProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var TaxRate $data */

        // Code, country and rate are required on create; on update an omitted
        // value arrives as null and leaves the existing one untouched.
        if ($isNew) {
            if (trim((string) $data->code) === '') {
                throw new BadRequestHttpException('Tax rate code is required.');
            }
            if (trim((string) $data->taxCountryId) === '') {
                throw new BadRequestHttpException('Tax country is required.');
            }
            if ($data->rate === null) {
                throw new BadRequestHttpException('Rate is required.');
            }
        }

        if ($data->rate !== null && $data->rate < 0) {
            throw new BadRequestHttpException('Rate must be a number greater than or equal to zero.');
        }
    }

    /**
     * The rate model's _afterSave() rewrites tax_calculation_rate_title from the
     * `title` field on every save, so it must always be populated: the submitted
     * titles when present, otherwise the existing ones to preserve them.
     */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var TaxRate $data */
        /** @var \Mage_Tax_Model_Calculation_Rate $model */
        if ($data->titles !== null) {
            $model->setTitle($this->normalizeTitles($data->titles));
            return;
        }

        if ($model->getId()) {
            $existing = [];
            foreach ($model->getTitles() as $title) {
                $existing[(int) $title->getStoreId()] = (string) $title->getValue();
            }
            $model->setTitle($existing);
        }
    }

    /**
     * @param array<mixed> $titles
     * @return array<int, string>
     */
    private function normalizeTitles(array $titles): array
    {
        $stores = \Mage::app()->getStores();
        $normalized = [];
        foreach ($titles as $entry) {
            if (!is_array($entry) || !isset($entry['storeId']) || !is_numeric($entry['storeId']) || !array_key_exists('title', $entry)) {
                throw new BadRequestHttpException('titles must be a list of {storeId, title} objects.');
            }
            $storeId = (int) $entry['storeId'];
            if (!isset($stores[$storeId])) {
                throw new BadRequestHttpException("Unknown store ID: {$storeId}");
            }
            $normalized[$storeId] = (string) $entry['title'];
        }
        return $normalized;
    }
}
