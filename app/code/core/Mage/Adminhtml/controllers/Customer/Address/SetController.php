<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer address attribute sets controller
 */
class Mage_Adminhtml_Customer_Address_SetController extends Mage_Eav_Controller_Adminhtml_Set_Abstract
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/attributes/customer_address_sets';

    #[\Override]
    protected function _construct()
    {
        $this->entityTypeCode = Mage_Customer_Model_Address::ENTITY;
    }

    #[\Override]
    protected function _initAction()
    {
        parent::_initAction();

        $this->_title($this->__('Customers'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Customer Address Attribute Sets'));

        $this->_setActiveMenu('customer/attributes/customer_address_sets')
             ->_addBreadcrumb(
                 $this->__('Customers'),
                 $this->__('Customers')
             )
             ->_addBreadcrumb(
                 $this->__('Manage Customer Address Attribute Sets'),
                 $this->__('Manage Customer Address Attribute Sets')
             );

        return $this;
    }
}
