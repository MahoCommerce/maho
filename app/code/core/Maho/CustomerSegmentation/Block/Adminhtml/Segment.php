<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
