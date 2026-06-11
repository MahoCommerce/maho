<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */


class Mage_Index_Block_Adminhtml_Process_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Mage_Index_Block_Adminhtml_Process_Edit constructor.
     */
    public function __construct()
    {
        $this->_objectId = 'process_id';
        $this->_controller = 'adminhtml_process';
        $this->_blockGroup = 'index';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('cms')->__('Save Process'));
        $this->_addButton('reindex', [
            'label'     => Mage::helper('index')->__('Reindex Data'),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getRunUrl()),
        ]);
        $this->_removeButton('reset');
        $this->_removeButton('delete');
    }

    /**
     * Get back button url
     *
     * @return string
     */
    #[\Override]
    public function getBackUrl()
    {
        return $this->getUrl('adminhtml/process/list');
    }

    /**
     * Get process reindex action url
     *
     * @return string
     */
    public function getRunUrl()
    {
        return $this->getUrl('adminhtml/process/reindexProcess', [
            'process' => Mage::registry('current_index_process')->getId(),
        ]);
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        $process = Mage::registry('current_index_process');
        if ($process && $process->getId()) {
            return Mage::helper('index')->__("'%s' Index Process Information", $process->getIndexer()->getName());
        }
        return '';
    }
}
