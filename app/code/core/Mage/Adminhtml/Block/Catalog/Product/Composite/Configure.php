<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Composite_Configure extends Mage_Adminhtml_Block_Widget
{
    protected $_product;

    /**
     * Set template
     */
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('catalog/product/composite/configure.phtml');
    }

    /**
     * Retrieve product object
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        if (!$this->_product) {
            if (Mage::registry('current_product')) {
                $this->_product = Mage::registry('current_product');
            } else {
                $this->_product = Mage::getSingleton('catalog/product');
            }
        }
        return $this->_product;
    }

    /**
     * Set product object
     *
     * @return $this
     */
    public function setProduct(?Mage_Catalog_Model_Product $product = null)
    {
        $this->_product = $product;
        return $this;
    }
}
