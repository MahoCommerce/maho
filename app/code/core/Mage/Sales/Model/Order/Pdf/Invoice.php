<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Pdf_Invoice extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Get layout handle for invoice PDF
     *
     * @return string
     */
    #[\Override]
    protected function _getLayoutHandle()
    {
        return 'sales_pdf_invoice';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    #[\Override]
    protected function _getBlockName()
    {
        return 'sales.pdf.invoice';
    }

    /**
     * Get block class name for direct instantiation
     */
    #[\Override]
    protected function _getBlockClass(): string
    {
        return 'Mage_Sales_Block_Order_Pdf_Invoice';
    }

    /**
     * Return PDF document
     *
     * @param array|\Maho\Data\Collection $invoices Array or collection of invoices
     */
    #[\Override]
    public function getPdf(array|\Maho\Data\Collection $invoices = []): string
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('invoice');

        // Handle collections
        if ($invoices instanceof \Maho\Data\Collection) {
            $invoices = $invoices->getItems();
        }

        if (empty($invoices)) {
            return '';
        }

        $html = $this->_renderDocumentsHtml($invoices);
        $pdf = $this->generatePdf($html);

        $this->_afterGetPdf();
        return $pdf;
    }
}
