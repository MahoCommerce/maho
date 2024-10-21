<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form select element with other option
 *
 * @category   Varien
 * @package    Varien_Data
 */
class Varien_Data_Form_Element_Customselect extends Varien_Data_Form_Element_Select
{
    /**
     * Varien_Data_Form_Element_Customselect constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('text');
        $this->setExtType('combobox');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('customselect');
        $html = parent::getElementHtml();

        $class = 'input-text';

        $customValue = $this->_escape($this->getCustomValue());
        if ($customValue === '') {
            $class .= ' no-display';
        }

        $disabled = '';
        if (in_array('disabled', $this->getHtmlAttributes()) && !empty($this->_data['disabled'])) {
            $disabled = 'disabled="disabled"';
        }

        $html .= <<<HTML
            <input id="{$this->getHtmlId()}__custom" type="text" value="$customValue" class="$class" $disabled />
            <script>
                (function () {
                    const selectEl = document.getElementById("{$this->getHtmlId()}");
                    const inputEl  = document.getElementById("{$this->getHtmlId()}__custom");

                    function onChange() {
                        inputEl.classList.toggle('no-display', selectEl.selectedIndex !== selectEl.options.length - 1);
                    }
                    selectEl.addEventListener("change", onChange);
                    onChange();

                    function onInput() {
                        selectEl.options[selectEl.options.length - 1].value = inputEl.value;
                    }
                    inputEl.addEventListener("input", onInput);
                    onInput();
                })();
            </script>
        HTML;

        return $html;
    }

    /**
     * Return the custom value if used, or an empty string
     */
    protected function getCustomValue(): string
    {
        if ($this->getData('custom_value') === null) {
            $value = $this->getValue();
            if ($filter = $this->getValueFilter()) {
                $value = $filter->filter($value);
            }
            if (in_array($value, array_column($this->getData('values'), 'label'))) {
                $value = '';
            }
            $this->setData('custom_value', (string)$value);
        }
        return $this->getData('custom_value');
    }

    /**
     * Return array of options, including an "Other" option
     */
    protected function getValues(): ?array
    {
        $values = $this->getData('values');
        if (!empty($values)) {
            foreach ($values as &$value) {
                if (is_array($value)) {
                    $value['value'] = $value['label'];
                } else {
                    $value = ['value' => (string)$value, 'label' => (string)$value];
                }
            }
            $values[] = ['value' => $this->getCustomValue(), 'label' => Mage::helper('core')->__('Other')];
        }
        return $values;
    }
}
