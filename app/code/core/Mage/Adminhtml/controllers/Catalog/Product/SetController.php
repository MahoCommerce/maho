<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml entity sets controller
 */
class Mage_Adminhtml_Catalog_Product_SetController extends Mage_Eav_Controller_Adminhtml_Set_Abstract
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/sets';

    #[\Override]
    protected function _construct()
    {
        $this->entityTypeCode = Mage_Catalog_Model_Product::ENTITY;
    }

    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        // For backwards compatibility set camelCase registry key with type id
        Mage::register('entityType', $this->entityType->getEntityTypeId());
    }

    #[\Override]
    protected function _initAction()
    {
        parent::_initAction();

        $this->_title($this->__('Catalog'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Attribute Sets'));

        $this->_setActiveMenu('catalog/attributes/sets')
             ->_addBreadcrumb(
                 $this->__('Catalog'),
                 $this->__('Catalog')
             )
             ->_addBreadcrumb(
                 $this->__('Manage Attribute Sets'),
                 $this->__('Manage Attribute Sets')
             );

        return $this;
    }
}
