<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Catalog manage products block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * Set template
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product.phtml');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->_addButton('add_new', [
            'label'   => Mage::helper('catalog')->__('Add Product'),
            'onclick' => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/new')),
            'class'   => 'add'
        ]);

        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/catalog_product_grid', 'product.grid'));
        return parent::_prepareLayout();
    }

    /**
     * @deprecated since 1.3.2
     *
     * @return string
     */
    public function getAddNewButtonHtml()
    {
        return $this->getChildHtml('add_new_button');
    }

    /**
     * Render grid
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid');
    }

    /**
     * Check whether it is single store mode
     *
     * @return bool
     */
    public function isSingleStoreMode()
    {
        if (!Mage::app()->isSingleStoreMode()) {
            return false;
        }
        return true;
    }
}
