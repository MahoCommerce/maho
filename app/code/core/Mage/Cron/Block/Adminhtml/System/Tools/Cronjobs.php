<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Block_Adminhtml_System_Tools_Cronjobs extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'cron';
        $this->_controller = 'adminhtml_system_tools_cronjobs';
        $this->_headerText = Mage::helper('cron')->__('Cron Jobs');
        parent::__construct();
        $this->_removeButton('add');
    }
}
