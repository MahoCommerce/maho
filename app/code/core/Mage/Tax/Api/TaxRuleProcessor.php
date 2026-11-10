<?php

/**
 * Give a tax rule write to the tax rule service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use Maho\ApiPlatform\CrudProcessor;

final class TaxRuleProcessor extends CrudProcessor
{
    #[\Override]
    protected function loadModel(int $id): \Mage_Tax_Model_Calculation_Rule
    {
        return $this->service()->getById($id);
    }

    #[\Override]
    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Calculation_Rule $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Calculation_Rule $model */
        $this->service()->delete($model);
    }

    private function service(): \Mage_Tax_Service_Rule
    {
        /** @var \Mage_Tax_Service_Rule */
        return \Mage::getService('tax/rule');
    }
}
