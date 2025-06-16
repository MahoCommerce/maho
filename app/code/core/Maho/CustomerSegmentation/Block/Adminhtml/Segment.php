<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Segment Adminhtml Block
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @author     Maho Team
 */
class Maho_CustomerSegmentation_Block_Adminhtml_Segment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_segment';
        $this->_blockGroup = 'customersegmentation';
        $this->_headerText = Mage::helper('customersegmentation')->__('Customer Segments');
        $this->_addButtonLabel = Mage::helper('customersegmentation')->__('Add New Segment');
        parent::__construct();
    }
}
