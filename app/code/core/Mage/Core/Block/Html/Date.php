<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getClass()
 * @method $this setClass(string $value)
 * @method string getExtraParams()
 * @method $this setExtraParams(string $value)
 * @method string getInputFormat()
 * @method $this setInputFormat(string $value)
 * @method string getDisplayFormat()
 * @method $this setDisplayFormat(string $value)
 * @method string getName()
 * @method $this setName(string $value)
 * @method string getTime()
 * @method $this setTime(bool $value)
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
            'inputFormat' => (string) ($this->getInputFormat() ?? $this->getFormat()),
            'enableTime' => $enableTime,
            'allowInput' => true,
            ...$this->config,
        ];

        if ($calendarYearsRange = $this->getYearsRange()) {
            $setupObj['range'] = $calendarYearsRange;
        }

        if ($this->getDisplayFormat()) {
            $setupObj['displayFormat'] = $this->getDisplayFormat();
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
