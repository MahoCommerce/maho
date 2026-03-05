<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Core_Block_Adminhtml_Email_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'core';
        $this->_controller = 'adminhtml_email_log';
        $this->_headerText = Mage::helper('core')->__('Email Log');
        parent::__construct();
        $this->_removeButton('add');
    }
}
