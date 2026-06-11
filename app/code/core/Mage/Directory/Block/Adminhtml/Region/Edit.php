<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Block_Adminhtml_Region_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_region';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('directory')->__('Save Region'));
        $this->_updateButton('delete', 'label', Mage::helper('directory')->__('Delete Region'));

        $this->_addButton('save_and_continue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => 'DirectoryEditForm.saveAndContinueEdit()',
            'class' => 'save',
        ], -100);
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $region = Mage::registry('current_region');
        if ($region->getId()) {
            return Mage::helper('directory')->__('Edit Region "%s"', $this->escapeHtml($region->getName()));
        }
        return Mage::helper('directory')->__('New Region');
    }
}
