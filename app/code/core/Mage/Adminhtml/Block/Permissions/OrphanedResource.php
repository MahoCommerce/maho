<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml permissions orphaned resource block
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Permissions_OrphanedResource extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'permissions_orphanedResource';
        $this->_headerText = Mage::helper('adminhtml')->__('Orphaned Role Resources');
        parent::__construct();
        $this->_removeButton('add');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        Mage::dispatchEvent('permissions_orphanedresource_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
