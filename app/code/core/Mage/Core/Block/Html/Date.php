<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * HTML select element block
 *
 * @package    Mage_Core
 *
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
    protected array $config = [];

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $enableTime = (bool) ($this->config['enableTime'] ?? $this->getTime());
        $setupObj = [
            'inputField' => (string) $this->getId(),
            'ifFormat'   => (string) Varien_Date::convertZendToStrftime($this->getFormat(), true, $enableTime),
            'enableTime' => $enableTime,
            'allowInput' => true,
            ...$this->config,
        ];

        if ($calendarYearsRange = $this->getYearsRange()) {
            $setupObj['range'] = $calendarYearsRange;
        }
        $setupObj = Mage::helper('core')->jsonEncode($setupObj);

        return <<<HTML
            <input type="text" name="{$this->getName()}" id="{$this->getId()}" value="{$this->escapeHtml($this->getValue())}" class="{$this->getClass()}" {$this->getExtraParams()} />
            <script>Calendar.setup({$setupObj});</script>
        HTML;
    }

    public function setConfig(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->config = [...$this->config, ...$key];
        } else {
            $this->config[$key] = $value;
        }
        return $this;
    }

    /**
     * @param null $index deprecated
     * @return string
     */
    public function getEscapedValue($index = null)
    {
        if ($this->getFormat() && $this->getValue()) {
            return strftime($this->getFormat(), strtotime($this->getValue()));
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
