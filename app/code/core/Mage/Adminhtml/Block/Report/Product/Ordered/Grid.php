<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml bestsellers products report grid block
 *
 * @deprecated after 1.4.0.1
 */
class Mage_Adminhtml_Block_Report_Product_Ordered_Grid extends Mage_Adminhtml_Block_Report_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('gridOrderedProducts');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareCollection()
    {
        parent::_prepareCollection();
        $this->getCollection()->initReport('reports/product_ordered_collection');
        return $this;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => Mage::helper('reports')->__('Product Name'),
            'index'     => 'name'
        ]);

        $baseCurrencyCode = $this->getCurrentCurrencyCode();

        $this->addColumn('price', [
            'header'        => Mage::helper('reports')->__('Price'),
            'width'         => '120px',
            'type'          => 'currency',
            'currency_code' => $baseCurrencyCode,
            'index'         => 'price',
            'rate'          => $this->getRate($baseCurrencyCode),
        ]);

        $this->addColumn('ordered_qty', [
            'header'    => Mage::helper('reports')->__('Quantity Ordered'),
            'width'     => '120px',
            'align'     => 'right',
            'index'     => 'ordered_qty',
            'total'     => 'sum',
            'type'      => 'number'
        ]);

        $this->addExportType('*/*/exportOrderedCsv', Mage::helper('reports')->__('CSV'));
        $this->addExportType('*/*/exportOrderedExcel', Mage::helper('reports')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
