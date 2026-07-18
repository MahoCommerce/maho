<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Cms_Block
{
    protected ?array $_options = null;

    public function toOptionArray(): array
    {
        if ($this->_options === null) {
            $this->_options = [['value' => '', 'label' => '']];
            $collection = Mage::getResourceModel('cms/block_collection')
                ->addFieldToFilter('is_active', 1)
                ->load();
            foreach ($collection as $block) {
                $this->_options[] = [
                    'value' => $block->getIdentifier(),
                    'label' => $block->getTitle(),
                ];
            }
        }
        return $this->_options;
    }
}
