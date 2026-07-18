<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
