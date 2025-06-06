<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_View_Grid_Renderer_Item extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Constructor to set default template
     *
     * @return $this
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('customer/edit/tab/view/grid/item.phtml');
        return $this;
    }

    /**
     * Returns helper for product type
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Helper_Product_Configuration_Interface
     */
    protected function _getProductHelper($product)
    {
        // Retrieve whole array of renderers
        $productHelpers = $this->getProductHelpers();
        if (!is_array($productHelpers)) {
            $column = $this->getColumn();
            if ($column) {
                $grid = $column->getGrid();
                if ($grid) {
                    $productHelpers = $grid->getProductConfigurationHelpers();
                    $this->setProductHelpers($productHelpers ?: []);
                }
            }
        }

        // Check whether we have helper for our product
        $productType = $product->getTypeId();
        $helperName = $productHelpers[$productType] ?? $productHelpers['default'] ?? 'catalog/product_configuration';

        $helper = Mage::helper($helperName);
        if (!($helper instanceof Mage_Catalog_Helper_Product_Configuration_Interface)) {
            Mage::throwException($this->__("Helper for options rendering doesn't implement required interface."));
        }

        return $helper;
    }

    /**
     * Returns product associated with this block
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return $this->getItem()->getProduct();
    }

    /**
     * Returns list of options and their values for product configuration
     *
     * @return array
     */
    protected function getOptionList()
    {
        $item = $this->getItem();
        $product = $item->getProduct();
        $helper = $this->_getProductHelper($product);
        return $helper->getOptions($item);
    }

    /**
     * Returns formatted option value for an item
     *
     * @param Mage_Wishlist_Model_Item_Option $option
     * @return array
     */
    protected function getFormattedOptionValue($option)
    {
        $params = [
            'max_length' => 55,
        ];
        return Mage::helper('catalog/product_configuration')->getFormattedOptionValue($option, $params);
    }

    /**
     * Renders item product name and its configuration
     *
     * @return string
     */
    #[\Override]
    public function render(Varien_Object $item)
    {
        $this->setItem($item);
        return $this->toHtml();
    }
}
