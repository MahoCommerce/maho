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
 * Catalog category attribute sets controller
 */
class Mage_Adminhtml_Catalog_Category_SetController extends Mage_Eav_Controller_Adminhtml_Set_Abstract
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/category_sets';

    #[\Override]
    protected function _construct()
    {
        $this->entityTypeCode = Mage_Catalog_Model_Category::ENTITY;
    }

    #[\Override]
    protected function _initAction()
    {
        parent::_initAction();

        $this->_title($this->__('Catalog'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Category Attribute Sets'));

        $this->_setActiveMenu('catalog/attributes/category_sets')
             ->_addBreadcrumb(
                 $this->__('Catalog'),
                 $this->__('Catalog')
             )
             ->_addBreadcrumb(
                 $this->__('Manage Category Attribute Sets'),
                 $this->__('Manage Category Attribute Sets')
             );

        return $this;
    }
}
