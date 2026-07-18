<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Block_Adminhtml_Region_Edit_Tab_Translations extends Mage_Adminhtml_Block_Text_List implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('directory')->__('Manage Translations');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('directory')->__('Manage Translations');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return $this->_isEditing();
    }

    #[\Override]
    public function isHidden(): bool
    {
        return !$this->_isEditing();
    }

    protected function _isEditing(): bool
    {
        $region = Mage::registry('current_region');
        return !is_null($region->getRegionId());
    }
}
