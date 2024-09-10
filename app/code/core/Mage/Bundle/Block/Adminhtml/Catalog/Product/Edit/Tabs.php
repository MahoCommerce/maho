<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml product edit tabs
 *
 * @category   Mage
 * @package    Mage_Bundle
 */
class Mage_Bundle_Block_Adminhtml_Catalog_Product_Edit_Tabs extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    protected $_attributeTabBlock = 'bundle/adminhtml_catalog_product_edit_tab_attributes';

    /**
     * @return $this
     * @throws Exception
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->addTab('bundle_items', [
            'label'     => Mage::helper('bundle')->__('Bundle Items'),
            'url'   => $this->getUrl('*/*/bundles', ['_current' => true]),
            'class' => 'ajax',
        ]);
        $this->bindShadowTabs('bundle_items', 'customer_options');

        return $this;
    }
}
