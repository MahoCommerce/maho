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

class Mage_Sales_Model_Order_Pdf_Shipment extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Draw table header for product items
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
            'feed' => 100,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('Qty'),
            'feed'  => 35,
        ];

        $lines[0][] = [
            'text'  => Mage::helper('sales')->__('SKU'),
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
     * @param  Mage_Sales_Model_Order_Shipment[] $shipments
     * @return string
     */
    #[\Override]
    public function getPdf($shipments = [])
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8');
        $pdf->setAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setMargins(0, 0, 0);

        $this->_setPdf($pdf);
        $this->_setFontBold(10);

        foreach ($shipments as $shipment) {
            if ($shipment->getStoreId()) {
                Mage::app()->getLocale()->emulate($shipment->getStoreId());
                Mage::app()->setCurrentStore($shipment->getStoreId());
            }
            $this->newPage();
            $order = $shipment->getOrder();
            /* Add image */
            $this->insertLogo($shipment->getStore());
            /* Add address */
            $this->insertAddress($shipment->getStore());
            /* Add head */
            $this->insertOrder(
                $shipment,
                Mage::getStoreConfigFlag(self::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID, $order->getStoreId()),
            );
            /* Add document text and number */
            $this->insertDocumentNumber(
                Mage::helper('sales')->__('Packingslip # ') . $shipment->getIncrementId(),
            );
            /* Add table */
            $this->_drawHeader();
            /* Add body */
            foreach ($shipment->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $order);
            }
        }
        $this->_afterGetPdf();
        if (isset($shipment) && $shipment->getStoreId()) {
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
        /* Add new table head */
        $this->_getPdf()->AddPage('P', 'A4');
        $this->y = 800;
        if (!empty($settings['table_header'])) {
            $this->_drawHeader();
        }
    }
}
