<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Adminhtml_Recurring_Profile_View_Tab_Orders extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Initialize basic parameters
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('recurring_profile_orders')
            ->setUseAjax(true)
            ->setSkipGenerateContent(true)
        ;
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('sales/order_grid_collection')
            ->addRecurringProfilesFilter(Mage::registry('current_recurring_profile')->getId())
        ;
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('real_order_id', [
            'header' => Mage::helper('sales')->__('Order #'),
            'width' => '80px',
            'type'  => 'text',
            'index' => 'increment_id',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header'    => Mage::helper('sales')->__('Purchased From (Store)'),
                'type'      => 'store',
                'display_deleted' => true,
            ]);
        }

        $this->addColumn('created_at', [
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index' => 'created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('billing_name', [
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
        ]);

        $this->addColumn('shipping_name', [
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
        ]);

        $this->addColumn('base_grand_total', [
            'header' => Mage::helper('sales')->__('G.T. (Base)'),
            'index' => 'base_grand_total',
            'type'  => 'currency',
            'currency' => 'base_currency_code',
        ]);

        $this->addColumn('grand_total', [
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
            'type'  => 'currency',
            'currency' => 'order_currency_code',
        ]);

        $this->addColumn('status', [
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
        ]);

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            $this->addColumn(
                'action',
                [
                    'type'      => 'action',
                    'getter'     => 'getId',
                    'actions'   => [
                        [
                            'caption' => Mage::helper('sales')->__('View'),
                            'url'     => ['base' => '*/sales_order/view'],
                            'field'   => 'order_id',
                        ],
                    ],
                    'index'     => 'stores',
                    'is_system' => true,
                    'data-column' => 'action',
                ],
            );
        }

        return parent::_prepareColumns();
    }

    /**
     * Return row url for js event handlers
     *
     * @param \Maho\DataObject $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $row->getId()]);
    }

    /**
     * Url for ajax grid submission
     *
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getTabUrl();
    }

    /**
     * Url for ajax tab
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('*/*/orders', ['profile' => Mage::registry('current_recurring_profile')->getId()]);
    }

    /**
     * Class for ajax tab
     *
     * @return string
     */
    public function getTabClass()
    {
        return 'ajax';
    }

    /**
     * Label getter
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('sales')->__('Related Orders');
    }

    /**
     * Same as label getter
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * @return bool
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
