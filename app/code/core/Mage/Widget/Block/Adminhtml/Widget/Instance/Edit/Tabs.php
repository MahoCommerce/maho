<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Widget_Block_Adminhtml_Widget_Instance_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('widget_instace_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('widget')->__('Widget Instance'));
    }
}
