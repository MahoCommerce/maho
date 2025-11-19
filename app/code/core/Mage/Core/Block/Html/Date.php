<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getClass()
 * @method $this setClass(string $value)
 * @method string getExtraParams()
 * @method $this setExtraParams(string $value)
 * @method string getFormat()
 * @method $this setFormat(string $value)
 * @method string getName()
 * @method $this setName(string $value)
 * @method string getTime()
 * @method $this setTime(string $value)
 * @method $this setTitle(string $value)
 * @method string getValue()
 * @method $this setValue(string $value)
 * @method string getYearsRange()
 * @method $this setYearsRange(string $value)
 */
class Mage_Core_Block_Html_Date extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        // Convert value to ISO format for native date input
        $isoValue = '';
        if ($this->getValue()) {
            try {
                // Parse the existing value and convert to ISO format
                $dateTime = new DateTime($this->getValue());
                if ($this->getTime()) {
                    $isoValue = $dateTime->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);
                } else {
                    $isoValue = $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT);
                }
            } catch (Exception $e) {
                // If parsing fails, use the original value
                $isoValue = $this->getValue();
            }
        }

        // Determine input type based on whether time is needed
        $inputType = $this->getTime() ? 'datetime-local' : 'date';

        $html = '<input type="' . $inputType . '" name="' . $this->getName() . '" id="' . $this->getId() . '" ';
        $html .= 'value="' . $this->escapeHtml($isoValue) . '" class="' . $this->getClass() . '" ';

        // Add min/max attributes if year range is specified
        $calendarYearsRange = $this->getYearsRange();
        if ($calendarYearsRange) {
            // Parse range like [2020, 2030]
            if (preg_match('/\[(\d{4}),\s*(\d{4})\]/', $calendarYearsRange, $matches)) {
                $yearStart = $matches[1];
                $yearEnd = $matches[2];
                if (!$this->getTime()) {
                    $html .= 'min="' . $yearStart . '-01-01" ';
                    $html .= 'max="' . $yearEnd . '-12-31" ';
                } else {
                    $html .= 'min="' . $yearStart . '-01-01T00:00" ';
                    $html .= 'max="' . $yearEnd . '-12-31T23:59" ';
                }
            }
        }

        $html .= $this->getExtraParams() . '/>';

        return $html;
    }

    /**
     * @param null $index deprecated
     * @return string
     */
    public function getEscapedValue($index = null)
    {
        if ($this->getFormat() && $this->getValue()) {
            return date($this->getFormat(), strtotime($this->getValue()));
        }

        return htmlspecialchars($this->getValue());
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->toHtml();
    }
}
