<?php

/**
 * Give a tax rate write to the tax rate service.
 *
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
    protected function loadModel(int $id): \Mage_Tax_Model_Calculation_Rate
    {
        return $this->service()->getById($id);
    }

    /**
     * A body without titles keeps the stored ones.
     */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var TaxRate $data */
        /** @var \Mage_Tax_Model_Calculation_Rate $model */
        if ($data->titles === null) {
            return;
        }
        $titles = [];
        foreach ($data->titles as $entry) {
            if (!is_array($entry) || !isset($entry['storeId']) || !is_numeric($entry['storeId']) || !array_key_exists('title', $entry)) {
                throw new BadRequestHttpException('titles must be a list of {storeId, title} objects.');
            }
            $titles[(int) $entry['storeId']] = (string) $entry['title'];
        }
        $model->setTitle($titles);
    }

    #[\Override]
    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Calculation_Rate $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Calculation_Rate $model */
        $this->service()->delete($model);
    }

    private function service(): \Mage_Tax_Service_Rate
    {
        /** @var \Mage_Tax_Service_Rate */
        return \Mage::getService('tax/rate');
    }
}
