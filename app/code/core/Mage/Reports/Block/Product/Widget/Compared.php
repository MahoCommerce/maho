<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Reports Recently Compared Products Widget
 *
 * @category   Mage
 * @package    Mage_Reports
 */
class Mage_Reports_Block_Product_Widget_Compared extends Mage_Reports_Block_Product_Compared implements Mage_Widget_Block_Interface
{
    /**
     * Internal constructor
     *
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->addColumnCountLayoutDepend('one_column', 5)
            ->addColumnCountLayoutDepend('two_columns_left', 4)
            ->addColumnCountLayoutDepend('two_columns_right', 4)
            ->addColumnCountLayoutDepend('three_columns', 3);
        $this->addPriceBlockType('bundle', 'bundle/catalog_product_price', 'bundle/catalog/product/price.phtml');
    }
}
