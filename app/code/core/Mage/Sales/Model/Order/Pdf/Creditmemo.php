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
     * Draw table header for product items
     */
    protected function _drawHeader()
    {
        $this->_setFontRegular(10);
        $this->_pdf->setFillColor(237, 235, 235);
        $this->_pdf->setDrawColor(128, 128, 128);
        $this->_pdf->setLineWidth(0.5);
        $this->_drawRectangle(25, $this->y, 570, $this->y - 30, 'DF');
        $this->y -= 10;
        $this->_pdf->setFillColor(0, 0, 0);

        //columns headers
        $lines[0][] = [
            'text' => Mage::helper('sales')->__('Products'),
            'feed' => 35,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('SKU'), 12, true, true),
            'feed'  => 255,
            'align' => 'right',
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('Total (ex)'), 12, true, true),
            'feed'  => 330,
            'align' => 'right',
            //'width' => 50,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('Discount'), 12, true, true),
            'feed'  => 380,
            'align' => 'right',
            //'width' => 50,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('Qty'), 12, true, true),
            'feed'  => 445,
            'align' => 'right',
            //'width' => 30,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('Tax'), 12, true, true),
            'feed'  => 495,
            'align' => 'right',
            //'width' => 45,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('core/string')->str_split(Mage::helper('sales')->__('Total (inc)'), 12, true, true),
            'feed'  => 565,
            'align' => 'right',
        ];

        $lineBlock = [
            'lines'  => $lines,
            'height' => 10,
        ];

        $this->drawLineBlocks([$lineBlock], ['table_header' => true]);
        $this->_pdf->setFillColor(0, 0, 0);
        $this->y -= 20;
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

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8');
        $pdf->setAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setMargins(0, 0, 0);

        $this->_setPdf($pdf);
        $this->_setFontBold(10);

        foreach ($creditmemos as $creditmemo) {
            if ($creditmemo->getStoreId()) {
                Mage::app()->getLocale()->emulate($creditmemo->getStoreId());
                Mage::app()->setCurrentStore($creditmemo->getStoreId());
            }
            $this->newPage();
            $order = $creditmemo->getOrder();
            /* Add image */
            $this->insertLogo($creditmemo->getStore());
            /* Add address */
            $this->insertAddress($creditmemo->getStore());
            /* Add head */
            $this->insertOrder(
                $order,
                Mage::getStoreConfigFlag(self::XML_PATH_SALES_PDF_CREDITMEMO_PUT_ORDER_ID, $order->getStoreId()),
            );
            /* Add document text and number */
            $this->insertDocumentNumber(
                Mage::helper('sales')->__('Credit Memo # ') . $creditmemo->getIncrementId(),
            );
            /* Add table head */
            $this->_drawHeader();
            /* Add body */
            foreach ($creditmemo->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $order);
            }
            /* Add totals */
            $this->insertTotals($creditmemo);
        }
        $this->_afterGetPdf();
        if (isset($creditmemo) && $creditmemo->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }
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
        parent::newPage($settings);
        if (!empty($settings['table_header'])) {
            $this->_drawHeader();
        }
    }
}
