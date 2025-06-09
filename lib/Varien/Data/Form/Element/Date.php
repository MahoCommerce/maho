<?php

/**
 * Maho
 *
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getFormat()
 * @method string getInputFormat()
 * @method string getDisplayFormat()
 * @method string getLocale()
 * @method string getImage()
 * @method string getTime()
 * @method bool getDisabled()
 */
class Varien_Data_Form_Element_Date extends Varien_Data_Form_Element_Abstract
{
    /**
     * @var Zend_Date|string
     */
    protected $_value;

    /**
     * Varien_Data_Form_Element_Date constructor.
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
     * If Zend_Date instance is provided instead of value, other params will be ignored.
     * Format and locale must be compatible with Zend_Date
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
        if ($value instanceof Zend_Date) {
            $this->_value = $value;
            return $this;
        }
        if (preg_match('/^[0-9]+$/', $value)) {
            $this->_value = new Zend_Date($this->_toTimestamp($value));
            return $this;
        }
        // last check, if input format was set
        if (null === $format) {
            $format = Varien_Date::DATETIME_INTERNAL_FORMAT;
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
            $this->_value = new Zend_Date($value, $format, $locale);
        } catch (Exception $e) {
            $this->_value = '';
        }
        return $this;
    }

    /**
     * Get date value as string.
     * Format can be specified, or it will be taken from $this->getInputFormat()
     *
     * @param string $format (compatible with Zend_Date)
     * @return string
     */
    public function getValue($format = null)
    {
        if (empty($this->_value)) {
            return '';
        }
        if (null === $format) {
            $format = $this->getInputFormat();
        }
        return $this->_value->toString($format);
    }

    /**
     * Get value instance, if any
     *
     * @return Zend_Date|string|null
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
     * - the value must be instantiated (Zend_Date)
     * - output format must be set (compatible with Zend_Date)
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('input-text');

        $html = sprintf(
            '<input name="%s" id="%s" value="%s" %s style="width:110px !important;" />',
            $this->getName(),
            $this->getHtmlId(),
            $this->_escape($this->getValue()),
            $this->serialize($this->getHtmlAttributes()),
        );

        $setupObj = [
            'inputField' => $this->getHtmlId(),
            'allowInput' => true,
            'enableTime' => (bool) $this->getTime(),
        ];

        // Modern ICU format
        if ($this->getInputFormat()) {
            $setupObj['inputFormat'] = (string) $this->getInputFormat();
        }

        // Legacy strftime format
        if ($this->getFormat()) {
            $setupObj['ifFormat'] = Varien_Date::convertZendToStrftime($this->getFormat(), true, $setupObj['enableTime']);
        }

        // Optional ICU display format
        if ($this->getDisplayFormat()) {
            $setupObj['displayFormat'] = $this->getDisplayFormat();
        }

        if (empty($setupObj['inputFormat']) && empty($setupObj['ifFormat'])) {
            throw new Exception('Output format is not specified. Please, specify "format" key in constructor, or set it using setInputFormat() or setFormat().');
        }

        $setupObj = json_encode($setupObj);

        $html .= <<<HTML
            <script>Calendar.setup({$setupObj});</script>
        HTML;

        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
