<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_CreditmemoController extends Mage_Adminhtml_Controller_Sales_Creditmemo
{
    /**
     * Export credit memo grid to CSV format
     */
    #[Maho\Config\Route('/admin/sales_creditmemo/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_creditmemo_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('creditmemos.csv', -1));
    }

    /**
     * Export credit memo grid to Excel XML format
     */
    #[Maho\Config\Route('/admin/sales_creditmemo/exportExcel')]
    public function exportExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_creditmemo_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('creditmemos.xml', -1));
    }

    /**
     * Index page
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_creditmemo/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))->_title($this->__('Credit Memos'));

        parent::indexAction();
    }
}
