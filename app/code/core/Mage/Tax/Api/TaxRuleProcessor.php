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
    protected function loadOrFail(string $modelAlias, int|string $id, string $notFoundMessage): \Mage_Tax_Model_Calculation_Rule
    {
        return $this->service()->getById((int) $id);
    }

    #[\Override]
    protected function safeSave(\Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        /** @var \Mage_Tax_Model_Calculation_Rule $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function safeDelete(\Mage_Core_Model_Abstract $model, string $entityLabel): void
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
