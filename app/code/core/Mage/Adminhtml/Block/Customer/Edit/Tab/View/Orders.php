<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_View_Orders extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Mage_Adminhtml_Block_Customer_Edit_Tab_View_Orders constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('customer_view_orders_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('desc');
        $this->setSortable(false);
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    #[\Override]
    protected function _preparePage()
    {
        $this->getCollection()
            ->setPageSize(5)
            ->setCurPage(1);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('sales/order_grid_collection')
            ->addFieldToFilter('customer_id', Mage::registry('current_customer')->getId())
            ->setIsCustomerMode(true);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('increment_id', [
            'header'    => Mage::helper('customer')->__('Order #'),
            'align'     => 'center',
            'index'     => 'increment_id',
            'width'     => '100px',
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('customer')->__('Purchased On'),
            'index'     => 'created_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('billing_name', [
            'header'    => Mage::helper('customer')->__('Bill to Name'),
            'index'     => 'billing_name',
        ]);

        $this->addColumn('shipping_name', [
            'header'    => Mage::helper('customer')->__('Shipped to Name'),
            'index'     => 'shipping_name',
        ]);

        $this->addColumn('grand_total', [
            'header'    => Mage::helper('customer')->__('Order Total'),
            'index'     => 'grand_total',
            'type'      => 'currency',
            'currency'  => 'order_currency_code',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header'    => Mage::helper('customer')->__('Bought From'),
                'type'      => 'store',
            ]);
        }

        $this->addColumn('action', [
            'type'      => 'action',
            'header'    =>  ' ',
            'width'     => '100',
            'renderer'  =>  'adminhtml/sales_reorder_renderer_action',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @param Varien_Object $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $row->getId()]);
    }

    /**
     * @return bool
     */
    #[\Override]
    public function getHeadersVisibility()
    {
        return ($this->getCollection()->getSize() > 0);
    }
}
