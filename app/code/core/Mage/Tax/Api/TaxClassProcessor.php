<?php

/**
 * Give a tax class write to the tax class service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use Maho\ApiPlatform\CrudProcessor;

final class TaxClassProcessor extends CrudProcessor
{
    #[\Override]
    protected function loadModel(int $id): \Mage_Tax_Model_Class
    {
        return $this->service()->getById($id);
    }

    #[\Override]
    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Class $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Tax_Model_Class $model */
        $this->service()->delete($model);
    }

    private function service(): \Mage_Tax_Service_Class
    {
        /** @var \Mage_Tax_Service_Class */
        return \Mage::getService('tax/class');
    }
}
