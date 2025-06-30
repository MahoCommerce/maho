<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Shipment_Packaging extends Mage_Sales_Model_Order_Pdf_Abstract
{
    /**
     * Format pdf file
     *
     * @param  Mage_Sales_Model_Order_Shipment $shipment
     * @return string
     */
    #[\Override]
    public function getPdf($shipment = null)
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8');
        $pdf->setAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setMargins(0, 0, 0);

        $this->_setPdf($pdf);
        $this->newPage();

        if ($shipment->getStoreId()) {
            Mage::app()->getLocale()->emulate($shipment->getStoreId());
            Mage::app()->setCurrentStore($shipment->getStoreId());
        }

        $this->_setFontRegular();
        $this->_drawHeaderBlock();

        $this->y = 740;
        $this->_drawPackageBlock();
        $this->_pdf->setFillColor(0, 0, 0);
        $this->_afterGetPdf();

        if ($shipment->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }
        return $pdf->Output('', 'S');
    }

    /**
     * Draw header block
     *
     * @return $this
     */
    protected function _drawHeaderBlock()
    {
        $this->_pdf->setFillColor(128, 128, 128);
        $this->_pdf->setDrawColor(128, 128, 128);
        $this->_pdf->setLineWidth(0.5);
        $this->_drawRectangle(25, 790, 570, 755, 'DF');
        $this->_pdf->setFillColor(255, 255, 255);
        $this->_drawText(Mage::helper('sales')->__('Packages'), 35, 770);
        $this->_pdf->setFillColor(237, 235, 235);

        return $this;
    }

    /**
     * Draw packages block
     *
     * @return $this
     */
    protected function _drawPackageBlock()
    {
        if ($this->getPackageShippingBlock()) {
            $packaging = $this->getPackageShippingBlock();
        } else {
            $packaging = Mage::getBlockSingleton('adminhtml/sales_order_shipment_packaging');
        }
        $packages = $packaging->getPackages();

        $packageNum = 1;
        foreach ($packages as $packageId => $package) {
            $this->_pdf->setFillColor(237, 235, 235);
            $this->_drawRectangle(25, $this->y + 15, 190, $this->y - 35, 'DF');
            $this->_drawRectangle(190, $this->y + 15, 350, $this->y - 35, 'DF');
            $this->_drawRectangle(350, $this->y + 15, 570, $this->y - 35, 'DF');

            $this->_pdf->setFillColor(255, 255, 255);
            $this->_drawRectangle(520, $this->y + 15, 570, $this->y - 5, 'DF');

            $this->_pdf->setFillColor(0, 0, 0);
            $packageText = Mage::helper('sales')->__('Package') . ' ' . $packageNum;
            $this->_drawText($packageText, 525, $this->y);
            $packageNum++;

            $package = new Varien_Object($package);
            $params = new Varien_Object($package->getParams());
            $dimensionUnits = Mage::helper('usa')->getMeasureDimensionName($params->getDimensionUnits());

            $typeText = Mage::helper('sales')->__('Type') . ' : '
                . $packaging->getContainerTypeByCode($params->getContainer());
            $this->_drawText($typeText, 35, $this->y);

            if ($params->getLength() != null) {
                $lengthText = $params->getLength() . ' ' . $dimensionUnits;
            } else {
                $lengthText = '--';
            }
            $lengthText = Mage::helper('sales')->__('Length') . ' : ' . $lengthText;
            $this->_drawText($lengthText, 200, $this->y);

            if ($params->getDeliveryConfirmation() != null) {
                $confirmationText = Mage::helper('sales')->__('Signature Confirmation')
                    . ' : '
                    . $packaging->getDeliveryConfirmationTypeByCode($params->getDeliveryConfirmation());
                $this->_drawText($confirmationText, 355, $this->y);
            }

            $this->y = $this->y - 10;

            if ($packaging->displayCustomsValue() != null) {
                $customsValueText = Mage::helper('sales')->__('Customs Value')
                    . ' : '
                    . $packaging->displayPrice($params->getCustomsValue());
                $this->_drawText($customsValueText, 35, $this->y);
            }
            if ($params->getWidth() != null) {
                $widthText = $params->getWidth() . ' ' . $dimensionUnits;
            } else {
                $widthText = '--';
            }
            $widthText = Mage::helper('sales')->__('Width') . ' : ' . $widthText;
            $this->_drawText($widthText, 200, $this->y);

            if ($params->getContentType() != null) {
                if ($params->getContentType() == 'OTHER') {
                    $contentsValue = $params->getContentTypeOther();
                } else {
                    $contentsValue = $packaging->getContentTypeByCode($params->getContentType());
                }
                $contentsText = Mage::helper('sales')->__('Contents')
                    . ' : '
                    . $contentsValue;
                $this->_drawText($contentsText, 355, $this->y);
            }

            $this->y = $this->y - 10;

            $weightText = Mage::helper('sales')->__('Total Weight') . ' : ' . $params->getWeight() . ' '
                . Mage::helper('usa')->getMeasureWeightName($params->getWeightUnits());
            $this->_drawText($weightText, 35, $this->y);

            if ($params->getHeight() != null) {
                $heightText = $params->getHeight() . ' ' . $dimensionUnits;
            } else {
                $heightText = '--';
            }
            $heightText = Mage::helper('sales')->__('Height') . ' : ' . $heightText;
            $this->_drawText($heightText, 200, $this->y);

            $this->y = $this->y - 10;

            if ($params->getSize()) {
                $sizeText = Mage::helper('sales')->__('Size') . ' : ' . ucfirst(strtolower($params->getSize()));
                $this->_drawText($sizeText, 35, $this->y);
            }
            if ($params->getGirth() != null) {
                $dimensionGirthUnits = Mage::helper('usa')->getMeasureDimensionName($params->getGirthDimensionUnits());
                $girthText = Mage::helper('sales')->__('Girth')
                             . ' : ' . $params->getGirth() . ' ' . $dimensionGirthUnits;
                $this->_drawText($girthText, 200, $this->y);
            }

            $this->y = $this->y - 5;
            $this->_pdf->setFillColor(255, 255, 255);
            $this->_drawRectangle(25, $this->y, 570, $this->y - 30 - (count($package->getItems()) * 12), 'DF');

            $this->y = $this->y - 10;
            $this->_pdf->setFillColor(0, 0, 0);
            $this->_drawText(Mage::helper('sales')->__('Items in the Package'), 30, $this->y);

            $txtIndent = 5;
            $itemCollsNumber = $packaging->displayCustomsValue() ? 5 : 4;
            $itemCollsX[0] = 30; //  coordinate for Product name
            $itemCollsX[1] = 250; // coordinate for Product name
            $itemCollsXEnd = 565;
            $itemCollsXStep = round(($itemCollsXEnd - $itemCollsX[1]) / ($itemCollsNumber - 1));
            // calculate coordinates for all other cells (Weight, Customs Value, Qty Ordered, Qty)
            for ($i = 2; $i <= $itemCollsNumber; $i++) {
                $itemCollsX[$i] = $itemCollsX[$i - 1] + $itemCollsXStep;
            }

            $i = 0;
            $this->_pdf->setFillColor(237, 235, 235);
            $this->_drawRectangle($itemCollsX[$i], $this->y - 5, $itemCollsX[++$i], $this->y - 15, 'DF');
            $this->_drawRectangle($itemCollsX[$i], $this->y - 5, $itemCollsX[++$i], $this->y - 15, 'DF');
            $this->_drawRectangle($itemCollsX[$i], $this->y - 5, $itemCollsX[++$i], $this->y - 15, 'DF');
            $this->_drawRectangle($itemCollsX[$i], $this->y - 5, $itemCollsX[++$i], $this->y - 15, 'DF');
            $this->_drawRectangle($itemCollsX[$i], $this->y - 5, $itemCollsXEnd, $this->y - 15, 'DF');

            $this->y = $this->y - 12;
            $i = 0;

            $this->_pdf->setFillColor(0, 0, 0);
            $this->_drawText(Mage::helper('sales')->__('Product'), $itemCollsX[$i] + $txtIndent, $this->y);
            $this->_drawText(Mage::helper('sales')->__('Weight'), $itemCollsX[++$i] + $txtIndent, $this->y);
            if ($packaging->displayCustomsValue()) {
                $this->_drawText(
                    Mage::helper('sales')->__('Customs Value'),
                    $itemCollsX[++$i] + $txtIndent,
                    $this->y,
                );
            }
            $this->_drawText(
                Mage::helper('sales')->__('Qty Ordered'),
                $itemCollsX[++$i] + $txtIndent,
                $this->y,
            );
            $this->_drawText(Mage::helper('sales')->__('Qty'), $itemCollsX[++$i] + $txtIndent, $this->y);

            $i = 0;
            foreach ($package->getItems() as $itemId => $item) {
                $item = new Varien_Object($item);
                $i = 0;

                $this->_pdf->setFillColor(255, 255, 255);
                $this->_drawRectangle($itemCollsX[$i], $this->y - 3, $itemCollsX[++$i], $this->y - 15, 'DF');
                $this->_drawRectangle($itemCollsX[$i], $this->y - 3, $itemCollsX[++$i], $this->y - 15, 'DF');
                $this->_drawRectangle($itemCollsX[$i], $this->y - 3, $itemCollsX[++$i], $this->y - 15, 'DF');
                $this->_drawRectangle($itemCollsX[$i], $this->y - 3, $itemCollsX[++$i], $this->y - 15, 'DF');
                $this->_drawRectangle($itemCollsX[$i], $this->y - 3, $itemCollsXEnd, $this->y - 15, 'DF');

                $this->y = $this->y - 12;
                $i = 0;
                $this->_pdf->setFillColor(0, 0, 0);
                $this->_drawText($item->getName(), $itemCollsX[$i] + $txtIndent, $this->y);
                $this->_drawText($item->getWeight(), $itemCollsX[++$i] + $txtIndent, $this->y);
                if ($packaging->displayCustomsValue()) {
                    $this->_drawText(
                        $packaging->displayPrice($item->getCustomsValue()),
                        $itemCollsX[++$i] + $txtIndent,
                        $this->y,
                    );
                }
                $this->_drawText(
                    $packaging->getQtyOrderedItem($item->getOrderItemId()),
                    $itemCollsX[++$i] + $txtIndent,
                    $this->y,
                );
                $this->_drawText($item->getQty() * 1, $itemCollsX[++$i] + $txtIndent, $this->y);
            }
            $this->y = $this->y - 30;
        }
        return $this;
    }
}
