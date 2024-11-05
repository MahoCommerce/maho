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
 * Customer attribute controller
 */
class Mage_Adminhtml_Customer_AttributeController extends Mage_Eav_Controller_Adminhtml_Attribute_Abstract
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/attributes/customer_attributes';

    #[\Override]
    protected function _construct()
    {
        $this->entityTypeCode = Mage_Customer_Model_Customer::ENTITY;
    }

    #[\Override]
    protected function _initAction()
    {
        parent::_initAction();

        $this->_title($this->__('Customers'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Customer Attributes'));

        $this->_setActiveMenu('customer/attributes/customer_attributes')
             ->_addBreadcrumb(
                 $this->__('Customers'),
                 $this->__('Customers')
             )
             ->_addBreadcrumb(
                 $this->__('Manage Customer Attributes'),
                 $this->__('Manage Customer Attributes')
             );

        return $this;
    }
}
