<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_ShipmentController extends Mage_Adminhtml_Controller_Sales_Shipment
{
    /**
     * Export shipment grid to CSV format
     */
    #[Maho\Config\Route('/admin/sales_shipment/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_shipment_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('shipments.csv', -1));
    }

    /**
     * Export shipment grid to Excel XML format
     */
    #[Maho\Config\Route('/admin/sales_shipment/exportExcel')]
    public function exportExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_shipment_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('shipments.xml', -1));
    }
}
