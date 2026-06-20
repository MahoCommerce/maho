<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Pdf_Creditmemo extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Get layout handle for creditmemo PDF
     *
     * @return string
     */
    #[\Override]
    protected function _getLayoutHandle()
    {
        return 'sales_pdf_creditmemo';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    #[\Override]
    protected function _getBlockName()
    {
        return 'sales.pdf.creditmemo';
    }

    /**
     * Get block class name for direct instantiation
     */
    #[\Override]
    protected function _getBlockClass(): string
    {
        return 'Mage_Sales_Block_Order_Pdf_Creditmemo';
    }

    /**
     * Return PDF document
     *
     * @param array|\Maho\Data\Collection $creditmemos Array or collection of creditmemos
     */
    #[\Override]
    public function getPdf(array|\Maho\Data\Collection $creditmemos = []): string
    {
        $this->_initRenderer('creditmemo');

        // Handle collections
        if ($creditmemos instanceof \Maho\Data\Collection) {
            $creditmemos = $creditmemos->getItems();
        }

        if (empty($creditmemos)) {
            return '';
        }

        $html = $this->_renderDocumentsHtml($creditmemos);
        $pdf = $this->generatePdf($html);
        return $pdf;
    }
}
