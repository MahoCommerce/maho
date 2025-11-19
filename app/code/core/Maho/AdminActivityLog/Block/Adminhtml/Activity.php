<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_activity';
        $this->_blockGroup = 'adminactivitylog';
        $this->_headerText = Mage::helper('adminactivitylog')->__('Admin Activity Log');

        parent::__construct();
        $this->_removeButton('add');
    }
}
