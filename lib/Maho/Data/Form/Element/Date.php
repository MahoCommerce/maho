<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use DateTime;
use Maho\Data\Form\Element\AbstractElement;

/**
 * @method string getFormat()
 * @method string getInputFormat()
 * @method string getLocale()
 * @method string getImage()
 * @method string getTime()
 * @method bool getDisabled()
 */
class Date extends AbstractElement
{
    /**
     * @var DateTime|string
     */
    protected $_value;

    /**
     * Date constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('text');
        $this->setExtType('textfield');
        if (isset($attributes['value'])) {
            $this->setValue($attributes['value']);
        }
    }

    /**
     * If script executes on x64 system, converts large
     * numeric values to timestamp limit
     *
     * @param string $value
     * @return int
     */
    protected function _toTimestamp($value)
    {
        $value = (int) $value;
        if ($value > 3155760000) {
            $value = 0;
        }

        return $value;
    }

    /**
     * Set date value
     * If DateTime instance is provided instead of value, other params will be ignored.
     * Format must be compatible with PHP DateTime
     *
     * @param mixed $value
     * @param string $format
     * @param string $locale
     * @return $this
     */
    public function setValue($value, $format = null, $locale = null)
    {
        if (empty($value)) {
            $this->_value = '';
            return $this;
        }

        // Handle MySQL zero dates and invalid dates
        if (is_string($value) && preg_match('/^0000-00-00/', $value)) {
            $this->_value = '';
            return $this;
        }

        if ($value instanceof DateTime) {
            $this->_value = $value;
            return $this;
        }
        if (preg_match('/^[0-9]+$/', $value)) {
            $this->_value = new DateTime('@' . $this->_toTimestamp($value));
            return $this;
        }
        // last check, if input format was set
        if (null === $format) {
            $format = \Mage_Core_Model_Locale::DATETIME_FORMAT;
            if ($this->getInputFormat()) {
                $format = $this->getInputFormat();
            }
        }
        // last check, if locale was set
        if (null === $locale) {
            if (!$locale = $this->getLocale()) {
                $locale = null;
            }
        }
        try {
            // Try to parse using the specified format first
            if ($format && $format !== \Mage_Core_Model_Locale::DATETIME_FORMAT) {
                // Convert ICU format to PHP format if needed (backward compatibility)
                $phpFormat = str_replace(['yyyy', 'MM', 'dd', 'HH', 'mm', 'ss'], ['Y', 'm', 'd', 'H', 'i', 's'], $format);
                $dateTime = DateTime::createFromFormat($phpFormat, $value);
                if ($dateTime === false) {
                    // Try standard DateTime parsing as fallback
                    $dateTime = new DateTime($value);
                }
                // Validate that the resulting date has a valid year
                if ($dateTime->format('Y') < 1) {
                    $this->_value = '';
                    return $this;
                }
                $this->_value = $dateTime;
            } else {
                $dateTime = new DateTime($value);
                // Validate that the resulting date has a valid year
                if ($dateTime->format('Y') < 1) {
                    $this->_value = '';
                    return $this;
                }
                $this->_value = $dateTime;
            }
        } catch (\Exception $e) {
            $this->_value = '';
        }
        return $this;
    }

    /**
     * Get date value as string.
     * Format can be specified, or it will be taken from $this->getFormat()
     *
     * @param string $format (compatible with PHP DateTime)
     * @return string
     */
    public function getValue($format = null)
    {
        if (empty($this->_value)) {
            return '';
        }
        if (null === $format) {
            $format = $this->getFormat();
        }
        if ($this->_value instanceof DateTime) {
            return $this->_value->format($format);
        }
        return (string) $this->_value;
    }

    /**
     * Get value instance, if any
     *
     * @return DateTime|string|null
     */
    public function getValueInstance()
    {
        if (empty($this->_value)) {
            return null;
        }
        return $this->_value;
    }

    /**
     * Output the input field and assign calendar instance to it.
     * In order to output the date:
     * - the value must be instantiated (DateTime)
     * - output format must be set (compatible with PHP DateTime)
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('input-text');

        // Convert the value to ISO format for native date input
        $isoValue = '';
        if ($this->_value instanceof DateTime) {
            // Validate that the date has a valid year (not from MySQL zero date)
            if ($this->_value->format('Y') >= 1) {
                if ($this->getTime()) {
                    $isoValue = $this->_value->format(\Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);
                } else {
                    $isoValue = $this->_value->format(\Mage_Core_Model_Locale::DATE_FORMAT);
                }
            }
        }

        // Determine input type based on whether time is needed
        $inputType = $this->getTime() ? 'datetime-local' : 'date';

        $html = sprintf(
            '<input type="%s" name="%s" id="%s" value="%s" %s style="width:auto !important;" />',
            $inputType,
            $this->getName(),
            $this->getHtmlId(),
            $this->_escape($isoValue),
            $this->serialize($this->getHtmlAttributes()),
        );

        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
