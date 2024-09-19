<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'Mage/Eav/Controller/Adminhtml/Attribute/Abstract.php';

/**
 * Catalog category attribute controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Catalog_Category_AttributeController extends Mage_Eav_Controller_Adminhtml_Attribute_Abstract
{
    protected function _construct()
    {
        $this->_entityCode = Mage_Catalog_Model_Category::ENTITY;
    }

    protected function _initAction()
    {
        parent::_initAction();

        $this->_title($this->__('Catalog'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Category Attributes'));

        $this->_setActiveMenu('catalog/attributes')
             ->_addBreadcrumb(
                 $this->__('Catalog'),
                 $this->__('Catalog')
             )
             ->_addBreadcrumb(
                 $this->__('Manage Category Attributes'),
                 $this->__('Manage Category Attributes')
             );

        return $this;
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/attributes/category_attributes');
    }
}
