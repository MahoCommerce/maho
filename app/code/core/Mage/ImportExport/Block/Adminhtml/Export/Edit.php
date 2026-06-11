<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Block_Adminhtml_Export_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->removeButton('back')
            ->removeButton('reset')
            ->removeButton('save');
    }

    /**
     * Internal constructor
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $this->_objectId   = 'export_id';
        $this->_blockGroup = 'importexport';
        $this->_controller = 'adminhtml_export';
    }

    /**
     * Get header text
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('importexport')->__('Export');
    }
}
