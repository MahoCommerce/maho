<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Shipment extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Get layout handle for shipment PDF
     *
     * @return string
     */
    #[\Override]
    protected function _getLayoutHandle()
    {
        return 'sales_pdf_shipment';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    #[\Override]
    protected function _getBlockName()
    {
        return 'sales.pdf.shipment';
    }

    /**
     * Get block class name for direct instantiation
     */
    #[\Override]
    protected function _getBlockClass(): string
    {
        return 'Mage_Sales_Block_Order_Pdf_Shipment';
    }

    /**
     * Return PDF document
     *
     * @param array|\Maho\Data\Collection $shipments Array or collection of shipments
     */
    #[\Override]
    public function getPdf(array|\Maho\Data\Collection $shipments = []): string
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        // Handle collections
        if ($shipments instanceof \Maho\Data\Collection) {
            $shipments = $shipments->getItems();
        }

        if (empty($shipments)) {
            return '';
        }

        $html = $this->_renderDocumentsHtml($shipments);
        $pdf = $this->generatePdf($html);

        $this->_afterGetPdf();
        return $pdf;
    }
}
