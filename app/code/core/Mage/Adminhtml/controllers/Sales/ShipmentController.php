<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Sales_ShipmentController extends Mage_Adminhtml_Controller_Sales_Shipment
{
    /**
     * Export shipment grid to CSV format
     */
    public function exportCsvAction()
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_shipment_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shipments.csv', -1));
    }

    /**
     * Export shipment grid to Excel XML format
     */
    public function exportExcelAction()
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_shipment_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shipments.xml', -1));
    }
}
