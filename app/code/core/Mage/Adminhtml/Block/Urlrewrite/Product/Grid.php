<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Urlrewrite_Product_Grid extends Mage_Adminhtml_Block_Catalog_Product_Grid
{
    /**
     * Disable massaction
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        return $this;
    }

    /**
     * Prepare columns layout
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            [
                'header' => Mage::helper('adminhtml')->__('ID'),
                'index' => 'entity_id',
            ],
        );

        $this->addColumn(
            'name',
            [
                'header' => Mage::helper('adminhtml')->__('Name'),
                'index' => 'name',
            ],
        );

        $this->addColumn(
            'sku',
            [
                'header' => Mage::helper('adminhtml')->__('SKU'),
                'width' => 80,
                'index' => 'sku',
            ],
        );
        $this->addColumn(
            'status',
            [
                'header' => Mage::helper('adminhtml')->__('Status'),
                'width' => 50,
                'index' => 'status',
                'type'  => 'options',
                'options' => Mage::getSingleton('catalog/product_status')->getOptionArray(),
            ],
        );
        return $this;
    }

    /**
     * Get url for dispatching grid ajax requests
     *
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/productGrid', ['_current' => true]);
    }

    /**
     * Get row url
     *
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['product' => $row->getId()]) . 'category';
    }
}
