<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml Report Sold Product Content Block
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Report_Product_Sold extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_product_sold';
        $this->_headerText = Mage::helper('reports')->__('Products Ordered');
        parent::__construct();
        $this->_removeButton('add');
    }
}
