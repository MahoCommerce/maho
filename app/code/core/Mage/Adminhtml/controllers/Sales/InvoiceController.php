<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_InvoiceController extends Mage_Adminhtml_Controller_Sales_Invoice
{
    /**
     * Export invoice grid to CSV format
     */
    #[Maho\Config\Route('/admin/sales_invoice/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_invoice_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('invoices.csv', -1));
    }

    /**
     * Export invoice grid to Excel XML format
     */
    #[Maho\Config\Route('/admin/sales_invoice/exportExcel')]
    public function exportExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_invoice_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('invoices.xml', -1));
    }
}
