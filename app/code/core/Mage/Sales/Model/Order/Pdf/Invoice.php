<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Invoice extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Draw header for item table
     */
    protected function _drawHeader()
    {
        /* Add table head */
        $this->_setFontRegular(10);
        $this->_pdf->setFillColor(237, 235, 235);
        $this->_pdf->setDrawColor(128, 128, 128);
        $this->_pdf->setLineWidth(0.5);
        $this->_drawRectangle(25, $this->y, 570, $this->y - 15, 'DF');
        $this->y -= 10;
        $this->_pdf->setFillColor(0, 0, 0);

        //columns headers
        $lines[0][] = [
            'text' => Mage::helper('sales')->__('Products'),
            'feed' => 35,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('SKU'),
            'feed'  => 290,
            'align' => 'right',
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('Qty'),
            'feed'  => 435,
            'align' => 'right',
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('Price'),
            'feed'  => 360,
            'align' => 'right',
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('Tax'),
            'feed'  => 495,
            'align' => 'right',
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('Subtotal'),
            'feed'  => 565,
            'align' => 'right',
        ];

        $lineBlock = [
            'lines'  => $lines,
            'height' => 5,
        ];

        $this->drawLineBlocks([$lineBlock], ['table_header' => true]);
        $this->_pdf->setFillColor(0, 0, 0);
        $this->y -= 20;
    }

    /**
     * Return PDF document
     *
     * @param  Mage_Sales_Model_Order_Invoice[] $invoices
     * @return string
     */
    #[\Override]
    public function getPdf($invoices = [])
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('invoice');

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8');
        $pdf->setAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setMargins(0, 0, 0);

        $this->_setPdf($pdf);
        $this->_setFontBold(10);

        foreach ($invoices as $invoice) {
            if ($invoice->getStoreId()) {
                Mage::app()->getLocale()->emulate($invoice->getStoreId());
                Mage::app()->setCurrentStore($invoice->getStoreId());
            }
            $this->newPage();
            $order = $invoice->getOrder();
            /* Add image */
            $this->insertLogo($invoice->getStore());
            /* Add address */
            $this->insertAddress($invoice->getStore());
            /* Add head */
            $this->insertOrder(
                $order,
                Mage::getStoreConfigFlag(self::XML_PATH_SALES_PDF_INVOICE_PUT_ORDER_ID, $order->getStoreId()),
            );
            /* Add document text and number */
            $this->insertDocumentNumber(
                Mage::helper('sales')->__('Invoice # ') . $invoice->getIncrementId(),
            );
            /* Add table */
            $this->_drawHeader();
            /* Add body */
            foreach ($invoice->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $order);
            }
            /* Add totals */
            $this->insertTotals($invoice);
            if ($invoice->getStoreId()) {
                Mage::app()->getLocale()->revert();
            }
        }
        $this->_afterGetPdf();
        return $pdf->Output('', 'S');
    }

    /**
     * Create new page and assign to PDF object
     *
     * @return void
     */
    #[\Override]
    public function newPage(array $settings = [])
    {
        /* Add new table head */
        $this->_getPdf()->addPage('P', 'A4');
        $this->y = 800;
        if (!empty($settings['table_header'])) {
            $this->_drawHeader();
        }
    }
}
