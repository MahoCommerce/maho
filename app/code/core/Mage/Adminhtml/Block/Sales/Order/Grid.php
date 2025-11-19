<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Mage_Adminhtml_Block_Widget_Grid_Massaction_Abstract as MassAction;

class Mage_Adminhtml_Block_Sales_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected ?array $orderStatusColors = null;

    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('real_order_id', [
            'header' => Mage::helper('sales')->__('Order #'),
            'width' => '100px',
            'type' => 'text',
            'index' => 'increment_id',
            'filter_index' => 'main_table.increment_id',
            'escape' => true,
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header' => Mage::helper('sales')->__('Purchased From (Store)'),
                'type' => 'store',
                'display_deleted' => true,
                'escape'  => true,
            ]);
        }

        $this->addColumn('created_at', [
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index' => 'created_at',
            'filter_index' => 'main_table.created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('billing_name', [
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
            'filter_index' => 'main_table.billing_name',
        ]);

        $this->addColumn('shipping_name', [
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
            'filter_index' => 'main_table.shipping_name',
        ]);

        $this->addColumn('base_grand_total', [
            'header' => Mage::helper('sales')->__('G.T. (Base)'),
            'index' => 'base_grand_total',
            'filter_index' => 'main_table.base_grand_total',
            'type'  => 'currency',
            'currency' => 'base_currency_code',
        ]);

        $this->addColumn('grand_total', [
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
            'filter_index' => 'main_table.grand_total',
            'type'  => 'currency',
            'currency' => 'order_currency_code',
        ]);

        $this->addColumn('status', [
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'status',
            'filter_index' => 'main_table.status',
            'type'  => 'options',
            'width' => '150px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
            'frame_callback' => [$this, 'decorateStatus'],
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
                            'data-column' => 'action',
                        ],
                    ],
                    'index'     => 'stores',
                    'is_system' => true,
                ],
            );
        }

        $this->addRssFeedLink();

        $this->addExportType('*/*/exportCsv', Mage::helper('sales')->__('CSV'));
        $this->addExportType('*/*/exportExcel', Mage::helper('sales')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    /**
     * Add link to RSS feed when enabled for filtered store-view
     *
     * @return $this
     * @throws Mage_Core_Model_Store_Exception
     */
    public function addRssFeedLink()
    {
        if ($this->isModuleOutputEnabled('Mage_Rss', 'sales')) {
            $storeId = null;

            $filterString = $this->getParam($this->getVarNameFilter(), '');
            if ($filterString) {
                $filter = Mage::helper('adminhtml')->prepareFilterString($filterString);
                $storeId = $filter['store_id'] ?? null;
            }

            if (Mage::helper('rss')->isRssAdminOrderNewEnabled($storeId)) {
                $slug = $storeId ? '/store/' . $storeId : '';
                $this->addRssList('rss/order/new' . $slug, Mage::helper('sales')->__('New Order RSS'));
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel')) {
            $this->getMassactionBlock()->addItem(MassAction::CANCEL_ORDER, [
                'label' => Mage::helper('sales')->__('Cancel'),
                'url'  => $this->getUrl('*/sales_order/massCancel'),
            ]);
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/hold')) {
            $this->getMassactionBlock()->addItem(MassAction::HOLD_ORDER, [
                'label' => Mage::helper('sales')->__('Hold'),
                'url'  => $this->getUrl('*/sales_order/massHold'),
            ]);
        }

        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/unhold')) {
            $this->getMassactionBlock()->addItem(MassAction::UNHOLD_ORDER, [
                'label' => Mage::helper('sales')->__('Unhold'),
                'url'  => $this->getUrl('*/sales_order/massUnhold'),
            ]);
        }

        $this->getMassactionBlock()->addItem(MassAction::PDF_INVOICE_ORDER, [
            'label' => Mage::helper('sales')->__('Print Invoices'),
            'url'  => $this->getUrl('*/sales_order/pdfinvoices'),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::PDF_SHIPMENTS_ORDER, [
            'label' => Mage::helper('sales')->__('Print Packingslips'),
            'url'  => $this->getUrl('*/sales_order/pdfshipments'),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::PDF_CREDITMEMOS_ORDER, [
            'label' => Mage::helper('sales')->__('Print Credit Memos'),
            'url'  => $this->getUrl('*/sales_order/pdfcreditmemos'),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::PDF_DOCS_ORDER, [
            'label' => Mage::helper('sales')->__('Print All'),
            'url'  => $this->getUrl('*/sales_order/pdfdocs'),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::PRINT_SHIPMENT_LABEL, [
            'label' => Mage::helper('sales')->__('Print Shipping Labels'),
            'url'  => $this->getUrl('*/sales_order_shipment/massPrintShippingLabel'),
        ]);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order $row
     * @return false|string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('*/sales_order/view', ['order_id' => $row->getId()]);
        }
        return false;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    public function decorateStatus($value, $row, $column, $isExport): string
    {
        if ($this->orderStatusColors === null) {
            $this->orderStatusColors = [];
            $orderStatusCollection = Mage::getResourceModel('sales/order_status_collection')->getItems();
            foreach ($orderStatusCollection as $orderStatus) {
                $color = $orderStatus->getColor();
                if ($color) {
                    $this->orderStatusColors[$orderStatus->getStatus()] = $color;
                }
            }
        }

        if ($this->orderStatusColors) {
            $color = $this->orderStatusColors[$row['status']] ?? '';
            return "<span class='order-status-color-marker' style='background:{$color}'></span> {$value}";
        }

        return $value;
    }
}
