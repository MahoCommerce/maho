<?php

/**
 * Maho
 *
 * @package    Maho_Revocation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Revocation_Block_Adminhtml_Request extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'revocation';
        $this->_controller = 'adminhtml_request';
        $this->_headerText = Mage::helper('revocation')->__('Revocation Requests');
        parent::__construct();
        $this->_removeButton('add');
    }
}
