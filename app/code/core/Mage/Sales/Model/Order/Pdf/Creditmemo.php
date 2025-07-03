<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Creditmemo extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Get layout handle for creditmemo PDF
     *
     * @return string
     */
    protected function _getLayoutHandle()
    {
        return 'sales_pdf_creditmemo';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    protected function _getBlockName()
    {
        return 'sales.pdf.creditmemo';
    }

    /**
     * Get block class name for direct instantiation
     *
     * @return string
     */
    protected function _getBlockClass()
    {
        return 'Mage_Sales_Block_Order_Pdf_Creditmemo';
    }

    /**
     * Return PDF document
     *
     * @param  Mage_Sales_Model_Order_Creditmemo[] $creditmemos
     * @return string
     */
    #[\Override]
    public function getPdf($creditmemos = [])
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('creditmemo');

        if (empty($creditmemos)) {
            return '';
        }

        $html = $this->_renderDocumentsHtml($creditmemos);
        $pdf = $this->_generatePdfFromHtml($html);

        $this->_afterGetPdf();
        return $pdf;
    }
}
