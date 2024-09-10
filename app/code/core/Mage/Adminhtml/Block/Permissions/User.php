<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml permissions user block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Permissions_User extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'permissions_user';
        $this->_headerText = Mage::helper('adminhtml')->__('Users');
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add New User');
        parent::__construct();
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('permissions_user_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
