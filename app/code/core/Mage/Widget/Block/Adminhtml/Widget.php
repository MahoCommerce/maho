<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

class Mage_Widget_Block_Adminhtml_Widget extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_blockGroup = 'widget';
        $this->_controller = 'adminhtml';
        $this->_mode = 'widget';
        $this->_headerText = '';

        $this->removeButton('reset');
        $this->removeButton('back');
        $this->removeButton('save'); // Always remove Insert Widget button - use OK button instead

        $this->_formScripts[] = 'wWidget = new WysiwygWidget.Widget('
            . '"widget_options_form", "select_widget_type", "widget_options", "'
            . $this->getUrl('*/*/loadOptions') . '", "' . $this->getRequest()->getParam('widget_target_id') . '");';
    }
}
