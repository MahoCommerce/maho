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

class Mage_Adminhtml_Block_Report_Refresh_Statistics_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);
        $this->setUseAjax(false);
    }

    /**
     * @param string $reportCode
     * @return string
     */
    protected function _getUpdatedAt($reportCode)
    {
        $flag = Mage::getModel('reports/flag')->setReportFlagCode($reportCode)->loadSelf();
        if (!$flag->hasData()) {
            return '';
        }

        $lastUpdate = $flag->getLastUpdate();
        if (empty($lastUpdate)) {
            return '';
        }

        try {
            // Try specific format first
            $dateObj = DateTime::createFromFormat(Mage_Core_Model_Locale::DATETIME_FORMAT, $lastUpdate);
            if ($dateObj === false) {
                // Try generic parsing
                $dateObj = new DateTime($lastUpdate);
            }

            return Mage::app()->getLocale()->storeDate(0, $dateObj, true);
        } catch (Exception $e) {
            // Graceful degradation - return raw value
            return $lastUpdate;
        }
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = new \Maho\Data\Collection();

        $data = [
            [
                'id'            => 'sales',
                'report'        => Mage::helper('sales')->__('Orders'),
                'comment'       => Mage::helper('sales')->__('Total Ordered Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_ORDER_FLAG_CODE),
            ],
            [
                'id'            => 'tax',
                'report'        => Mage::helper('sales')->__('Tax'),
                'comment'       => Mage::helper('sales')->__('Order Taxes Report Grouped by Tax Rates'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_TAX_FLAG_CODE),
            ],
            [
                'id'            => 'shipping',
                'report'        => Mage::helper('sales')->__('Shipping'),
                'comment'       => Mage::helper('sales')->__('Total Shipped Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_SHIPPING_FLAG_CODE),
            ],
            [
                'id'            => 'invoiced',
                'report'        => Mage::helper('sales')->__('Total Invoiced'),
                'comment'       => Mage::helper('sales')->__('Total Invoiced VS Paid Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_INVOICE_FLAG_CODE),
            ],
            [
                'id'            => 'refunded',
                'report'        => Mage::helper('sales')->__('Total Refunded'),
                'comment'       => Mage::helper('sales')->__('Total Refunded Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_REFUNDED_FLAG_CODE),
            ],
            [
                'id'            => 'coupons',
                'report'        => Mage::helper('sales')->__('Coupons'),
                'comment'       => Mage::helper('sales')->__('Promotion Coupons Usage Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_COUPONS_FLAG_CODE),
            ],
            [
                'id'            => 'bestsellers',
                'report'        => Mage::helper('sales')->__('Bestsellers'),
                'comment'       => Mage::helper('sales')->__('Products Bestsellers Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_BESTSELLERS_FLAG_CODE),
            ],
            [
                'id'            => 'viewed',
                'report'        => Mage::helper('sales')->__('Most Viewed'),
                'comment'       => Mage::helper('sales')->__('Most Viewed Products Report'),
                'updated_at'    => $this->_getUpdatedAt(Mage_Reports_Model_Flag::REPORT_PRODUCT_VIEWED_FLAG_CODE),
            ],
        ];

        foreach ($data as $value) {
            $item = new \Maho\DataObject();
            $item->setData($value);
            $collection->addItem($item);
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('report', [
            'header'    => Mage::helper('reports')->__('Report'),
            'index'     => 'report',
            'type'      => 'string',
            'width'     => 150,
            'sortable'  => false,
        ]);

        $this->addColumn('comment', [
            'header'    => Mage::helper('reports')->__('Description'),
            'index'     => 'comment',
            'type'      => 'string',
            'sortable'  => false,
        ]);

        $this->addColumn('updated_at', [
            'header'    => Mage::helper('reports')->__('Updated At'),
            'index'     => 'updated_at',
            'type'      => 'datetime',
            'default'   => Mage::helper('reports')->__('undefined'),
            'sortable'  => false,
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('code');

        $this->getMassactionBlock()->addItem(MassAction::REFRESH_LIFETIME, [
            'label'    => Mage::helper('reports')->__('Refresh Lifetime Statistics'),
            'url'      => $this->getUrl('*/*/refreshLifetime'),
            'confirm'  => Mage::helper('reports')->__('Are you sure you want to refresh lifetime statistics? There can be performance impact during this operation.'),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::REFRESH_RECENT, [
            'label'    => Mage::helper('reports')->__('Refresh Statistics for the Last Day'),
            'url'      => $this->getUrl('*/*/refreshRecent'),
            'confirm'  => Mage::helper('reports')->__('Are you sure?'),
            'selected' => true,
        ]);

        return $this;
    }
}
