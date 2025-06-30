<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * DHL International (API v1.4) Label Creation - TCPDF Implementation
 *
 * @deprecated now the process of creating the label is on DHL side
 */
class Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_Page
{
    /**
     * Text align constants
     */
    public const ALIGN_RIGHT = 'right';
    public const ALIGN_LEFT = 'left';
    public const ALIGN_CENTER = 'center';

    /**
     * TCPDF instance
     *
     * @var TCPDF
     */
    protected $_pdf;

    /**
     * Current font family
     *
     * @var string
     */
    protected $_fontFamily = 'helvetica';

    /**
     * Current font size
     *
     * @var float
     */
    protected $_fontSize = 10;

    /**
     * Saved font family for state restoration
     *
     * @var string
     */
    protected $_savedFontFamily;

    /**
     * Saved font size for state restoration
     *
     * @var float
     */
    protected $_savedFontSize;

    /**
     * DHL International Label Creation Class constructor
     *
     * @param TCPDF|string $param1 TCPDF instance or page size
     */
    public function __construct($param1)
    {
        if ($param1 instanceof TCPDF) {
            $this->_pdf = $param1;
        } else {
            // Create new TCPDF instance with specified page size
            $pageSize = is_string($param1) ? $param1 : 'A4';
            $this->_pdf = new TCPDF('L', 'pt', $pageSize, true, 'UTF-8');
            $this->_pdf->setAutoPageBreak(false);
            $this->_pdf->setPrintHeader(false);
            $this->_pdf->setPrintFooter(false);
            $this->_pdf->setMargins(0, 0, 0);
        }
    }

    /**
     * Get PDF contents
     *
     * @return string
     */
    public function getContents()
    {
        return $this->_pdf->Output('', 'S');
    }

    /**
     * Calculate the width of given text in points taking into account current font and font-size
     *
     * @param string $text
     * @param string $font
     * @param float $fontSize
     * @return float
     */
    public function getTextWidth($text, $font, $fontSize)
    {
        // Save current settings
        $currentFont = $this->_pdf->getFontFamily();
        $currentFontStyle = $this->_pdf->getFontStyle();
        $currentFontSize = $this->_pdf->getFontSizePt();

        // Set the font for measurement
        $this->_pdf->setFont($font, '', $fontSize);
        $width = $this->_pdf->GetStringWidth($text);

        // Restore previous settings
        $this->_pdf->setFont($currentFont, $currentFontStyle, $currentFontSize);

        return $width;
    }

    /**
     * Get current font
     *
     * @return string
     */
    public function getFont()
    {
        return $this->_fontFamily;
    }

    /**
     * Get current font size
     *
     * @return float
     */
    public function getFontSize()
    {
        return $this->_fontSize;
    }

    /**
     * Set font
     *
     * @param string $font
     * @param float $fontSize
     * @return void
     */
    public function setFont($font, $fontSize)
    {
        $this->_fontFamily = $font;
        $this->_fontSize = $fontSize;
        $this->_pdf->setFont($font, '', $fontSize);
    }

    /**
     * Convert Y coordinate from bottom-left to top-left origin
     *
     * @param float $y
     * @return float
     */
    protected function _convertY($y)
    {
        return $this->_pdf->getPageHeight() - $y;
    }

    /**
     * Draw a line of text at the specified position.
     *
     * @param string $text
     * @param float $x
     * @param float $y
     * @param string $charEncoding (optional) Character encoding of source text.
     * @param string $align
     * @return self
     */
    public function drawText($text, $x, $y, $charEncoding = 'UTF-8', $align = self::ALIGN_LEFT)
    {
        $left = null;
        switch ($align) {
            case self::ALIGN_LEFT:
                $left = $x;
                break;

            case self::ALIGN_CENTER:
                $textWidth = $this->getTextWidth($text, $this->getFont(), $this->getFontSize());
                $left = $x - ($textWidth / 2);
                break;

            case self::ALIGN_RIGHT:
                $textWidth = $this->getTextWidth($text, $this->getFont(), $this->getFontSize());
                $left = $x - $textWidth;
                break;
        }

        $this->_pdf->Text($left, $this->_convertY($y), $text);
        return $this;
    }

    /**
     * Draw a text paragraph taking into account the maximum number of symbols in a row.
     * If line is longer - split it.
     *
     * @param array $lines
     * @param int $x
     * @param int $y
     * @param int $maxWidth - number of symbols
     * @param string $align
     * @return float
     */
    public function drawLines($lines, $x, $y, $maxWidth, $align = self::ALIGN_LEFT)
    {
        foreach ($lines as $line) {
            if (strlen($line) > $maxWidth) {
                $subLines = Mage::helper('core/string')->str_split($line, $maxWidth, true, true);
                $y = $this->drawLines(array_filter($subLines), $x, $y, $maxWidth, $align);
                continue;
            }
            $this->drawText($line, $x, $y, 'UTF-8', $align);
            $y -= ceil($this->getFontSize());
        }
        return $y;
    }

    /**
     * Draw rectangle
     *
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @param string $style
     * @return self
     */
    public function drawRectangle($x1, $y1, $x2, $y2, $style = 'D')
    {
        $width = $x2 - $x1;
        $height = abs($y2 - $y1);
        $y = $this->_convertY(max($y1, $y2));
        $this->_pdf->Rect($x1, $y, $width, $height, $style);
        return $this;
    }

    /**
     * Draw line
     *
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @return self
     */
    public function drawLine($x1, $y1, $x2, $y2)
    {
        $this->_pdf->Line($x1, $this->_convertY($y1), $x2, $this->_convertY($y2));
        return $this;
    }

    /**
     * Draw image
     *
     * @param string $imagePath
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @return self
     */
    public function drawImage($imagePath, $x1, $y1, $x2, $y2)
    {
        $width = $x2 - $x1;
        $height = $y2 - $y1;
        $this->_pdf->Image($imagePath, $x1, $this->_convertY($y2), $width, $height);
        return $this;
    }

    /**
     * Set fill color
     *
     * @param array|object|string $color RGB color array, color object, or hex string
     * @return self
     */
    public function setFillColor($color)
    {
        if (is_array($color)) {
            // Handle RGB arrays - check if values are 0-1 (Zend_Pdf style) or 0-255 (TCPDF style)
            $r = $color[0] <= 1 ? $color[0] * 255 : $color[0];
            $g = $color[1] <= 1 ? $color[1] * 255 : $color[1];
            $b = $color[2] <= 1 ? $color[2] * 255 : $color[2];
            $this->_pdf->setFillColor($r, $g, $b);
        } elseif (is_object($color) && method_exists($color, 'getRed')) {
            // Handle legacy Zend_Pdf_Color objects (deprecated but maintained for compatibility)
            $this->_pdf->setFillColor($color->getRed() * 255, $color->getGreen() * 255, $color->getBlue() * 255);
        } elseif (is_string($color) && strpos($color, '#') === 0) {
            // Handle hex color strings
            $hex = ltrim($color, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $this->_pdf->setFillColor($r, $g, $b);
        }
        return $this;
    }

    /**
     * Set line width
     *
     * @param float $width
     * @return self
     */
    public function setLineWidth($width)
    {
        $this->_pdf->setLineWidth($width);
        return $this;
    }

    /**
     * Save graphics state - TCPDF equivalent
     * Save current font settings for later restoration
     *
     * @return self
     */
    public function saveGS()
    {
        // Store current font settings
        $this->_savedFontFamily = $this->_fontFamily;
        $this->_savedFontSize = $this->_fontSize;
        return $this;
    }

    /**
     * Restore graphics state - TCPDF equivalent
     * Restore previously saved font settings
     *
     * @return self
     */
    public function restoreGS()
    {
        // Restore font settings if they were saved
        if ($this->_savedFontFamily !== null && $this->_savedFontSize !== null) {
            $this->setFont($this->_savedFontFamily, $this->_savedFontSize);
        }
        return $this;
    }

    /**
     * Get TCPDF instance
     *
     * @return TCPDF
     */
    public function getPdf()
    {
        return $this->_pdf;
    }
}
