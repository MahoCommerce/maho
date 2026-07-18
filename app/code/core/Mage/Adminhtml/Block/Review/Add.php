<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Review_Add extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_controller = 'review';
        $this->_mode = 'add';

        $this->_updateButton('save', 'label', Mage::helper('review')->__('Save Review'));
        $this->_updateButton('save', 'id', 'save_button');

        $this->_updateButton('reset', 'id', 'reset_button');

        $this->_formInitScripts[] = <<<JS
            const review = new ReviewEditForm({
                ratingItemsUrl: '{$this->getUrl('*/*/ratingItems')}',
                productEditUrl: '{$this->getUrl('*/catalog_product/edit')}',
            });
        JS;
        $this->_formScripts[] = <<<JS
            review.hideForm();
        JS;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('review')->__('Add New Review');
    }
}
