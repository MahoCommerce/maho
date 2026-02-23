<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_apiplatform_role';
        $this->_blockGroup = 'maho_apiplatform';
        $this->_headerText = Mage::helper('maho_apiplatform')->__('REST/GraphQL - Roles');
        $this->_addButtonLabel = Mage::helper('maho_apiplatform')->__('Add New Role');
        parent::__construct();
    }
}
