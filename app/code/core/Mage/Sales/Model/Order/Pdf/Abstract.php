<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Sales_Model_Order_Pdf_Abstract extends Varien_Object
{
    /**
     * Y coordinate
     *
     * @var int
     */
    public $y;

    /**
     * Item renderers with render type key
     *
     * model    => the model name
     * renderer => the renderer model
     *
     * @var array
     */
    protected $_renderers = [];

    /**
     * Predefined constants
     */
    public const XML_PATH_SALES_PDF_INVOICE_PUT_ORDER_ID       = 'sales_pdf/invoice/put_order_id';
    public const XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID      = 'sales_pdf/shipment/put_order_id';
    public const XML_PATH_SALES_PDF_CREDITMEMO_PUT_ORDER_ID    = 'sales_pdf/creditmemo/put_order_id';

    /**
     * TCPDF object
     *
     * @var TCPDF
     */
    protected $_pdf;

    /**
     * Default total model
     *
     * @var string
     */
    protected $_defaultTotalModel = 'sales/order_pdf_total_default';

    /**
     * Retrieve PDF
     *
     * @return string
     */
    abstract public function getPdf();


    /**
     * Draw text at specified coordinates
     *
     * @param string $text
     * @param float $x
     * @param float $y
     * @return void
     */
    protected function _drawText($text, $x, $y)
    {
        $this->_pdf->Text($x, $y, $text);
    }

    /**
     * Draw rectangle
     *
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @param string $style
     * @return void
     */
    protected function _drawRectangle($x1, $y1, $x2, $y2, $style = 'D')
    {
        $width = $x2 - $x1;
        $height = abs($y2 - $y1);
        $this->_pdf->Rect($x1, $y1, $width, $height, $style);
    }

    /**
     * Draw line
     *
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @return void
     */
    protected function _drawLine($x1, $y1, $x2, $y2)
    {
        $this->_pdf->Line($x1, $y1, $x2, $y2);
    }

    /**
     * Returns the total width in points of the string using TCPDF.
     *
     * @param  string $string
     * @param  string $font
     * @param  float $fontSize Font size in points
     * @return float
     */
    public function widthForStringUsingFontSize($string, $font, $fontSize)
    {
        if (!$this->_pdf instanceof TCPDF) {
            return strlen($string) * $fontSize * 0.6; // Fallback estimation
        }

        // Save current font settings
        $currentFont = $this->_pdf->getFontFamily();
        $currentFontStyle = $this->_pdf->getFontStyle();
        $currentFontSize = $this->_pdf->getFontSizePt();

        // Set the font for measurement
        $this->_pdf->setFont($font, '', $fontSize);
        $width = $this->_pdf->GetStringWidth($string);

        // Restore previous font settings
        $this->_pdf->setFont($currentFont, $currentFontStyle, $currentFontSize);

        return $width;
    }

    /**
     * Calculate coordinates to draw something in a column aligned to the right
     *
     * @param  string $string
     * @param  int $x
     * @param  int $columnWidth
     * @param  string $font
     * @param  int $fontSize
     * @param  int $padding
     * @return int
     */
    public function getAlignRight($string, $x, $columnWidth, $font, $fontSize, $padding = 5)
    {
        $width = $this->widthForStringUsingFontSize($string, $font, $fontSize);
        return $x + $columnWidth - $width - $padding;
    }

    /**
     * Calculate coordinates to draw something in a column aligned to the center
     *
     * @param  string $string
     * @param  int $x
     * @param  int $columnWidth
     * @param  string $font
     * @param  int $fontSize
     * @return int
     */
    public function getAlignCenter($string, $x, $columnWidth, $font, $fontSize)
    {
        $width = $this->widthForStringUsingFontSize($string, $font, $fontSize);
        return $x + round(($columnWidth - $width) / 2);
    }

    /**
     * Insert logo to pdf page
     *
     * @param null|string|bool|int|Mage_Core_Model_Store $store $store
     */
    protected function insertLogo($store = null)
    {
        $this->y = $this->y ?: 30; // Start from top of page
        $image = Mage::getStoreConfig('sales/identity/logo', $store);
        if ($image) {
            $imagePath = Mage::getBaseDir('media') . '/sales/store/logo/' . $image;
            if (is_file($imagePath)) {
                $imageSize = getimagesize($imagePath);
                if ($imageSize !== false) {
                    $widthLimit = 270; // Half of the page width
                    $heightLimit = 270; // Assuming the image is not a "skyscraper"
                    $originalWidth = $imageSize[0];
                    $originalHeight = $imageSize[1];

                    // Preserving aspect ratio (proportions)
                    $ratio = $originalWidth / $originalHeight;
                    if ($ratio > 1 && $originalWidth > $widthLimit) {
                        $width = $widthLimit;
                        $height = $width / $ratio;
                    } elseif ($ratio < 1 && $originalHeight > $heightLimit) {
                        $height = $heightLimit;
                        $width = $height * $ratio;
                    } elseif ($ratio == 1 && $originalHeight > $heightLimit) {
                        $height = $heightLimit;
                        $width = $widthLimit;
                    } else {
                        $width = $originalWidth;
                        $height = $originalHeight;
                    }

                    $x = 25;
                    $this->_pdf->Image($imagePath, $x, $this->y, $width, $height);

                    // Update y position (move down for next elements)
                    $this->y += $height + 10;
                }
            }
        }
    }

    /**
     * Insert address to pdf page
     *
     * @param null|string|bool|int|Mage_Core_Model_Store $store $store
     */
    protected function insertAddress($store = null)
    {
        $this->_pdf->setFillColor(0, 0, 0);
        $this->_setFontRegular(10);
        $this->_pdf->setLineWidth(0);

        $this->y = $this->y ?: 30; // Start from top of page

        $addressLines = explode("\n", Mage::getStoreConfig('sales/identity/address', $store));
        foreach ($addressLines as $value) {
            if ($value !== '') {
                $value = preg_replace('/<br[^>]*>/i', "\n", $value);
                foreach (Mage::helper('core/string')->str_split($value, 45, true, true) as $str) {
                    $text = trim(strip_tags($str));
                    $x = $this->getAlignRight($text, 130, 440, 'helvetica', 10);
                    $this->_pdf->Text($x, $this->y, $text);
                    $this->y += 10; // Move down
                }
            }
        }
    }

    /**
     * Format address
     *
     * @param  string $address
     * @return array
     */
    protected function _formatAddress($address)
    {
        $return = [];
        foreach (explode('|', $address) as $str) {
            foreach (Mage::helper('core/string')->str_split($str, 45, true, true) as $part) {
                if (empty($part)) {
                    continue;
                }
                $return[] = $part;
            }
        }
        return $return;
    }

    /**
     * Calculate address height
     *
     * @param  array $address
     * @return int Height
     */
    protected function _calcAddressHeight($address)
    {
        $y = 0;
        foreach ($address as $value) {
            if ($value !== '') {
                $text = [];
                foreach (Mage::helper('core/string')->str_split($value, 55, true, true) as $str) {
                    $text[] = $str;
                }
                foreach ($text as $part) {
                    $y += 15;
                }
            }
        }
        return $y;
    }

    /**
     * Insert order to pdf page
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Order_Shipment $obj
     * @param bool $putOrderId
     */
    protected function insertOrder($obj, $putOrderId = true)
    {
        if ($obj instanceof Mage_Sales_Model_Order) {
            $shipment = null;
            $order = $obj;
        } elseif ($obj instanceof Mage_Sales_Model_Order_Shipment) {
            $shipment = $obj;
            $order = $shipment->getOrder();
        }

        $this->y = $this->y ?: 200; // Start after logo/address area
        $top = $this->y;

        $this->_pdf->setFillColor(115, 115, 115);
        $this->_pdf->setDrawColor(115, 115, 115);
        $this->_drawRectangle(25, $top, 570, $top + 55, 'DF');
        $this->_pdf->setFillColor(255, 255, 255);
        $this->setDocHeaderCoordinates([25, $top, 570, $top + 55]);
        $this->_setFontRegular(10);

        if ($putOrderId) {
            $top += 30;
            $this->_drawText(
                Mage::helper('sales')->__('Order # ') . $order->getRealOrderId(),
                35,
                $top,
            );
        }
        $top += 15;
        $this->_drawText(
            Mage::helper('sales')->__('Order Date: ') . Mage::helper('core')->formatDate(
                $order->getCreatedAtStoreDate(),
                'medium',
                false,
            ),
            35,
            $top,
        );

        $top += 10;
        $this->_pdf->setFillColor(237, 235, 235);
        $this->_pdf->setDrawColor(128, 128, 128);
        $this->_pdf->setLineWidth(0.5);
        $this->_drawRectangle(25, $top, 275, $top + 25, 'DF');
        $this->_drawRectangle(275, $top, 570, $top + 25, 'DF');

        /* Calculate blocks info */

        /* Billing Address */
        $billingAddress = $this->_formatAddress($order->getBillingAddress()->format('pdf'));

        /* Payment */
        $paymentInfo = Mage::helper('payment')->getInfoBlock($order->getPayment())
            ->setIsSecureMode(true)
            ->toPdf();
        $paymentInfo = htmlspecialchars_decode($paymentInfo, ENT_QUOTES);
        $payment = explode('{{pdf_row_separator}}', $paymentInfo);
        foreach ($payment as $key => $value) {
            if (strip_tags(trim($value)) == '') {
                unset($payment[$key]);
            }
        }
        reset($payment);

        /* Shipping Address and Method */
        if (!$order->getIsVirtual()) {
            /* Shipping Address */
            $shippingAddress = $this->_formatAddress($order->getShippingAddress()->format('pdf'));
            $shippingMethod  = $order->getShippingDescription();
        }

        $this->_pdf->setFillColor(0, 0, 0);
        $this->_setFontBold(12);
        $top += 15;
        $this->_drawText(Mage::helper('sales')->__('Sold to:'), 35, $top);

        if (!$order->getIsVirtual()) {
            $this->_drawText(Mage::helper('sales')->__('Ship to:'), 285, $top);
        } else {
            $this->_drawText(Mage::helper('sales')->__('Payment Method:'), 285, $top);
        }

        $addressesHeight = $this->_calcAddressHeight($billingAddress);
        if (isset($shippingAddress)) {
            $addressesHeight = max($addressesHeight, $this->_calcAddressHeight($shippingAddress));
        }

        $this->_pdf->setFillColor(255, 255, 255);
        $this->_drawRectangle(25, $top + 25, 570, $top + 33 + $addressesHeight, 'DF');
        $this->_pdf->setFillColor(0, 0, 0);
        $this->_setFontRegular(10);
        $this->y = $top + 40;
        $addressesStartY = $this->y;

        foreach ($billingAddress as $value) {
            if ($value !== '') {
                $text = [];
                foreach (Mage::helper('core/string')->str_split($value, 45, true, true) as $str) {
                    $text[] = $str;
                }
                foreach ($text as $part) {
                    $this->_drawText(strip_tags(ltrim($part)), 35, $this->y);
                    $this->y += 15;
                }
            }
        }

        $addressesEndY = $this->y;

        if (!$order->getIsVirtual()) {
            $this->y = $addressesStartY;
            if (isset($shippingAddress) && is_iterable($shippingAddress)) {
                foreach ($shippingAddress as $value) {
                    if ($value !== '') {
                        $text = [];
                        foreach (Mage::helper('core/string')->str_split($value, 45, true, true) as $str) {
                            $text[] = $str;
                        }
                        foreach ($text as $part) {
                            $this->_drawText(strip_tags(ltrim($part)), 285, $this->y);
                            $this->y += 15;
                        }
                    }
                }
            }

            $addressesEndY = max($addressesEndY, $this->y);
            $this->y = $addressesEndY;

            $this->_pdf->setFillColor(237, 235, 235);
            $this->_pdf->setLineWidth(0.5);
            $this->_drawRectangle(25, $this->y, 275, $this->y + 25, 'DF');
            $this->_drawRectangle(275, $this->y, 570, $this->y + 25, 'DF');

            $this->y += 15;
            $this->_setFontBold(12);
            $this->_pdf->setFillColor(0, 0, 0);
            $this->_drawText(Mage::helper('sales')->__('Payment Method'), 35, $this->y);
            $this->_drawText(Mage::helper('sales')->__('Shipping Method:'), 285, $this->y);

            $this->y += 10;
            $this->_pdf->setFillColor(255, 255, 255);

            $this->_setFontRegular(10);
            $this->_pdf->setFillColor(0, 0, 0);

            $paymentLeft = 35;
            $yPayments   = $this->y + 15;
        } else {
            $yPayments   = $addressesStartY;
            $paymentLeft = 285;
        }

        foreach ($payment as $value) {
            if (trim($value) != '') {
                //Printing "Payment Method" lines
                $value = preg_replace('/<br[^>]*>/i', "\n", $value);
                foreach (Mage::helper('core/string')->str_split($value, 45, true, true) as $str) {
                    $this->_drawText(strip_tags(trim($str)), $paymentLeft, $yPayments);
                    $yPayments += 15;
                }
            }
        }

        if ($order->getIsVirtual()) {
            // replacement of Shipments-Payments rectangle block
            $yPayments = max($addressesEndY, $yPayments);
            $this->_drawLine(25, $top + 25, 25, $yPayments);
            $this->_drawLine(570, $top + 25, 570, $yPayments);
            $this->_drawLine(25, $yPayments, 570, $yPayments);

            $this->y = $yPayments + 15;
        } else {
            $topMargin    = 15;
            $methodStartY = $this->y;
            $this->y     += 15;

            foreach (Mage::helper('core/string')->str_split($shippingMethod, 45, true, true) as $str) {
                $this->_drawText(strip_tags(trim($str)), 285, $this->y);
                $this->y += 15;
            }

            $yShipments = $this->y;
            $totalShippingChargesText = '(' . Mage::helper('sales')->__('Total Shipping Charges') . ' '
                . $order->formatPriceTxt($order->getShippingAmount()) . ')';

            $this->_drawText($totalShippingChargesText, 285, $yShipments + $topMargin);
            $yShipments += $topMargin + 10;

            $tracks = [];
            if ($shipment) {
                /** @var Mage_Sales_Model_Order_Shipment $shipment */
                $tracks = $shipment->getAllTracks();
            }
            if (count($tracks)) {
                $this->_pdf->setFillColor(237, 235, 235);
                $this->_pdf->setLineWidth(0.5);
                $this->_drawRectangle(285, $yShipments, 510, $yShipments + 10, 'DF');
                $this->_drawLine(400, $yShipments, 400, $yShipments + 10);

                $this->_setFontRegular(9);
                $this->_pdf->setFillColor(0, 0, 0);
                $this->_drawText(Mage::helper('sales')->__('Title'), 290, $yShipments + 7);
                $this->_drawText(Mage::helper('sales')->__('Number'), 410, $yShipments + 7);

                $yShipments += 20;
                $this->_setFontRegular(8);
                foreach ($tracks as $track) {
                    $carrierCode = $track->getCarrierCode();
                    if ($carrierCode != 'custom') {
                        $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance($carrierCode);
                        $carrierTitle = $carrier->getConfigData('title');
                    } else {
                        $carrierTitle = Mage::helper('sales')->__('Custom Value');
                    }

                    //$truncatedCarrierTitle = substr($carrierTitle, 0, 35) . (strlen($carrierTitle) > 35 ? '...' : '');
                    $maxTitleLen = 45;
                    $endOfTitle = strlen($track->getTitle()) > $maxTitleLen ? '...' : '';
                    $truncatedTitle = substr($track->getTitle(), 0, $maxTitleLen) . $endOfTitle;
                    //$page->drawText($truncatedCarrierTitle, 285, $yShipments , 'UTF-8');
                    $this->_drawText($truncatedTitle, 292, $yShipments);
                    $this->_drawText($track->getNumber() ?? '', 410, $yShipments);
                    $yShipments += $topMargin - 5;
                }
            } else {
                $yShipments += $topMargin - 5;
            }

            $currentY = max($yPayments, $yShipments);

            // replacement of Shipments-Payments rectangle block
            $this->_drawLine(25, $methodStartY, 25, $currentY); //left
            $this->_drawLine(25, $currentY, 570, $currentY); //bottom
            $this->_drawLine(570, $currentY, 570, $methodStartY); //right

            $this->y = $currentY;
            $this->y += 15;
        }
    }

    /**
     * Insert title and number for concrete document type
     *
     * @param  string $text
     */
    public function insertDocumentNumber($text)
    {
        $this->_pdf->setFillColor(255, 255, 255);
        $this->_setFontRegular(10);
        $docHeader = $this->getDocHeaderCoordinates();
        $this->_drawText($text, 35, $docHeader[1] + 15);
    }

    /**
     * Sort totals list
     *
     * @param  array $a
     * @param  array $b
     * @return int
     */
    protected function _sortTotalsList($a, $b)
    {
        if (!isset($a['sort_order']) || !isset($b['sort_order'])) {
            return 0;
        }
        return $a['sort_order'] <=> $b['sort_order'];
    }

    /**
     * Return total list
     *
     * @param  Mage_Sales_Model_Abstract $source
     * @return array
     */
    protected function _getTotalsList($source)
    {
        $totals = Mage::getConfig()->getNode('global/pdf/totals')->asArray();
        usort($totals, [$this, '_sortTotalsList']);
        $totalModels = [];
        foreach ($totals as $index => $totalInfo) {
            if (!empty($totalInfo['model'])) {
                $totalModel = Mage::getModel($totalInfo['model']);
                if ($totalModel instanceof Mage_Sales_Model_Order_Pdf_Total_Default) {
                    $totalInfo['model'] = $totalModel;
                } else {
                    Mage::throwException(
                        Mage::helper('sales')->__('PDF total model should extend Mage_Sales_Model_Order_Pdf_Total_Default'),
                    );
                }
            } else {
                $totalModel = Mage::getModel($this->_defaultTotalModel);
            }
            $totalModel->setData($totalInfo);
            $totalModels[] = $totalModel;
        }

        return $totalModels;
    }

    /**
     * Insert totals to pdf page
     *
     * @param  Mage_Sales_Model_Abstract $source
     * @return void
     */
    protected function insertTotals($source)
    {
        $order = $source->getOrder();
        $totals = $this->_getTotalsList($source);
        $lineBlock = [
            'lines'  => [],
            'height' => 15,
        ];
        foreach ($totals as $total) {
            $total->setOrder($order)
                ->setSource($source);

            if ($total->canDisplay()) {
                $total->setFontSize(10);
                foreach ($total->getTotalsForDisplay() as $totalData) {
                    $lineBlock['lines'][] = [
                        [
                            'text'      => $totalData['label'],
                            'feed'      => 475,
                            'align'     => 'right',
                            'font_size' => $totalData['font_size'],
                            'font'      => 'bold',
                        ],
                        [
                            'text'      => $totalData['amount'],
                            'feed'      => 565,
                            'align'     => 'right',
                            'font_size' => $totalData['font_size'],
                            'font'      => 'bold',
                        ],
                    ];
                }
            }
        }

        $this->y += 20;
        $this->drawLineBlocks([$lineBlock]);
    }

    /**
     * Parse item description
     *
     * @param  Varien_Object $item
     * @return array
     */
    protected function _parseItemDescription($item)
    {
        $matches = [];
        $description = $item->getDescription();
        if (preg_match_all('/<li.*?>(.*?)<\/li>/i', $description, $matches)) {
            return $matches[1];
        }

        return [$description];
    }

    /**
     * Before getPdf processing
     */
    protected function _beforeGetPdf()
    {
        $translate = Mage::getSingleton('core/translate');
        /** @var Mage_Core_Model_Translate $translate */
        $translate->setTranslateInline(false);
    }

    /**
     * After getPdf processing
     */
    protected function _afterGetPdf()
    {
        $translate = Mage::getSingleton('core/translate');
        /** @var Mage_Core_Model_Translate $translate */
        $translate->setTranslateInline(true);
    }

    /**
     * Format option value process
     *
     * @param  array|string $value
     * @param  Mage_Sales_Model_Order $order
     * @return string
     */
    protected function _formatOptionValue($value, $order)
    {
        $resultValue = '';
        if (is_array($value)) {
            if (isset($value['qty'])) {
                $resultValue .= sprintf('%d', $value['qty']) . ' x ';
            }

            $resultValue .= $value['title'];

            if (isset($value['price'])) {
                $resultValue .= ' ' . $order->formatPrice($value['price']);
            }
            return  $resultValue;
        } else {
            return $value;
        }
    }

    /**
     * Initialize renderer process
     *
     * @param string $type
     */
    protected function _initRenderer($type)
    {
        $node = Mage::getConfig()->getNode('global/pdf/' . $type);
        foreach ($node->children() as $renderer) {
            $this->_renderers[$renderer->getName()] = [
                'model'     => (string) $renderer,
                'renderer'  => null,
            ];
        }
    }

    /**
     * Retrieve renderer model
     *
     * @param  string $type
     * @throws Mage_Core_Exception
     * @return Mage_Sales_Model_Order_Pdf_Items_Abstract
     */
    protected function _getRenderer($type)
    {
        if (!isset($this->_renderers[$type])) {
            $type = 'default';
        }

        if (!isset($this->_renderers[$type])) {
            Mage::throwException(Mage::helper('sales')->__('Invalid renderer model'));
        }

        if (is_null($this->_renderers[$type]['renderer'])) {
            $this->_renderers[$type]['renderer'] = Mage::getSingleton($this->_renderers[$type]['model']);
        }

        return $this->_renderers[$type]['renderer'];
    }

    /**
     * Public method of protected @see _getRenderer()
     *
     * Retrieve renderer model
     *
     * @param  string $type
     * @return Mage_Sales_Model_Order_Pdf_Items_Abstract
     */
    public function getRenderer($type)
    {
        return $this->_getRenderer($type);
    }

    /**
     * Render item
     *
     * @param Mage_Sales_Model_Order_Pdf_Items_Abstract $renderer
     *
     * @return Mage_Sales_Model_Order_Pdf_Abstract
     */
    public function renderItem(Varien_Object $item, Mage_Sales_Model_Order $order, $renderer)
    {
        $renderer->setOrder($order)
            ->setItem($item)
            ->setPdf($this)
            ->setRenderedModel($this)
            ->draw();

        return $this;
    }

    /**
     * Draw Item process
     *
     * @return void
     */
    protected function _drawItem(Varien_Object $item, Mage_Sales_Model_Order $order)
    {
        $orderItem = $item->getOrderItem();
        $type = $orderItem->getProductType();
        $renderer = $this->_getRenderer($type);

        $this->renderItem($item, $order, $renderer);

        $transportObject = new Varien_Object(['renderer_type_list' => []]);
        Mage::dispatchEvent('pdf_item_draw_after', [
            'transport_object' => $transportObject,
            'entity_item'      => $item,
        ]);

        foreach ($transportObject->getRendererTypeList() as $type) {
            $renderer = $this->_getRenderer($type);
            if ($renderer) {
                $this->renderItem($orderItem, $order, $renderer);
            }
        }
    }

    /**
     * Set font as regular
     *
     * @param  int $size
     * @return string
     */
    public function _setFontRegular($size = 7)
    {
        $this->_pdf->setFont('helvetica', '', $size);
        return 'helvetica';
    }

    /**
     * Set font as bold
     *
     * @param  int $size
     * @return string
     */
    public function _setFontBold($size = 7)
    {
        $this->_pdf->setFont('helvetica', 'B', $size);
        return 'helvetica';
    }

    /**
     * Set font as italic
     *
     * @param  int $size
     * @return string
     */
    public function _setFontItalic($size = 7)
    {
        $this->_pdf->setFont('helvetica', 'I', $size);
        return 'helvetica';
    }

    /**
     * Set PDF object
     *
     * @return Mage_Sales_Model_Order_Pdf_Abstract
     */
    protected function _setPdf(TCPDF $pdf)
    {
        $this->_pdf = $pdf;
        return $this;
    }

    /**
     * Retrieve PDF object
     *
     * @throws Mage_Core_Exception
     * @return TCPDF
     */
    protected function _getPdf()
    {
        if (!$this->_pdf instanceof TCPDF) {
            Mage::throwException(Mage::helper('sales')->__('Please define PDF object before using.'));
        }

        return $this->_pdf;
    }

    /**
     * Create new page and assign to PDF object
     *
     * @return void
     */
    public function newPage(array $settings = [])
    {
        $pageFormat = !empty($settings['page_size']) ? $settings['page_size'] : 'A4';
        $this->_getPdf()->AddPage('P', $pageFormat);
        $this->y = 30; // Start from top of page (TCPDF coordinates)
    }

    /**
     * Draw lines
     *
     * draw items array format:
     * lines        array;array of line blocks (required)
     * shift        int; full line height (optional)
     * height       int;line spacing (default 10)
     *
     * line block has line columns array
     *
     * column array format
     * text         string|array; draw text (required)
     * feed         int; x position (required)
     * font         string; font style, optional: bold, italic, regular
     * font_file    string; path to font file (optional for use your custom font)
     * font_size    int; font size (default 7)
     * align        string; text align (also see feed parameter), optional left, right
     * height       int;line spacing (default 10)
     *
     * @throws Mage_Core_Exception
     * @return void
     */
    public function drawLineBlocks(array $draw, array $pageSettings = [])
    {
        foreach ($draw as $itemsProp) {
            if (!isset($itemsProp['lines']) || !is_array($itemsProp['lines'])) {
                Mage::throwException(Mage::helper('sales')->__('Invalid draw line data. Please define "lines" array.'));
            }
            $lines  = $itemsProp['lines'];
            $height = $itemsProp['height'] ?? 10;

            if (empty($itemsProp['shift'])) {
                $shift = 0;
                foreach ($lines as $line) {
                    $maxHeight = 0;
                    foreach ($line as $column) {
                        $lineSpacing = !empty($column['height']) ? $column['height'] : $height;
                        if (!is_array($column['text'])) {
                            $column['text'] = [$column['text']];
                        }
                        $top = 0;
                        foreach ($column['text'] as $part) {
                            $top += $lineSpacing;
                        }

                        $maxHeight = $top > $maxHeight ? $top : $maxHeight;
                    }
                    $shift += $maxHeight;
                }
                $itemsProp['shift'] = $shift;
            }

            if ($this->y + $itemsProp['shift'] > 750) { // Adjust for page height
                $this->newPage($pageSettings);
            }

            foreach ($lines as $line) {
                $maxHeight = 0;
                foreach ($line as $column) {
                    $fontSize = empty($column['font_size']) ? 10 : $column['font_size'];
                    if (!empty($column['font_file'])) {
                        // Custom font handling - for now, fall back to helvetica
                        $font = 'helvetica';
                        $this->_pdf->setFont($font, '', $fontSize);
                    } else {
                        $fontStyle = empty($column['font']) ? 'regular' : $column['font'];
                        $font = match ($fontStyle) {
                            'bold' => $this->_setFontBold($fontSize),
                            'italic' => $this->_setFontItalic($fontSize),
                            default => $this->_setFontRegular($fontSize),
                        };
                    }

                    if (!is_array($column['text'])) {
                        $column['text'] = [$column['text']];
                    }

                    $lineSpacing = !empty($column['height']) ? $column['height'] : $height;
                    $top = 0;
                    foreach ($column['text'] as $part) {
                        if ($this->y + $lineSpacing > 750) { // Adjust for page height
                            $this->newPage($pageSettings);
                        }

                        $feed = $column['feed'];
                        $textAlign = empty($column['align']) ? 'left' : $column['align'];
                        $width = empty($column['width']) ? 0 : $column['width'];
                        switch ($textAlign) {
                            case 'right':
                                if ($width) {
                                    $feed = $this->getAlignRight($part, $feed, $width, $font, $fontSize);
                                } else {
                                    $feed = $feed - $this->widthForStringUsingFontSize($part, $font, $fontSize);
                                }
                                break;
                            case 'center':
                                if ($width) {
                                    $feed = $this->getAlignCenter($part, $feed, $width, $font, $fontSize);
                                }
                                break;
                        }
                        $this->_drawText($part, $feed, $this->y + $top);
                        $top += $lineSpacing;
                    }

                    $maxHeight = $top > $maxHeight ? $top : $maxHeight;
                }
                $this->y += $maxHeight;
            }
        }
    }
}
