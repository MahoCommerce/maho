<?php

/**
 * Give a CMS block write to the CMS block service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use Maho\ApiPlatform\CrudProcessor;

final class CmsBlockProcessor extends CrudProcessor
{
    #[\Override]
    protected function newModel(): \Mage_Cms_Model_Block
    {
        return $this->service()->newBlock();
    }

    #[\Override]
    protected function loadModel(int $id): \Mage_Cms_Model_Block
    {
        return $this->service()->getById($id);
    }

    #[\Override]
    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Cms_Model_Block $model */
        $this->service()->save($model);
    }

    #[\Override]
    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        /** @var \Mage_Cms_Model_Block $model */
        $this->service()->delete($model);
    }

    private function service(): \Mage_Cms_Service_Block
    {
        /** @var \Mage_Cms_Service_Block */
        return \Mage::getService('cms/block');
    }
}
