<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form;
use Maho\Data\Form\AbstractForm;
use Maho\Data\Form\Element\Renderer\RendererInterface;

/**
 * @method $this setAfterElementHtml(string $value)
 * @method string getClass()
 * @method $this setClass(string $value)
 * @method $this setContainer(Form $value)
 * @method $this setExtType(string $value)
 * @method Form getForm()
 * @method string getLabel()
 * @method $this setLabel(string $value)
 * @method bool getNoSpan()
 * @method $this setName(string $value)
 * @method bool getRequired()
 * @method string getValue()
 * @method array getValues()
 * @method $this setValues(array|int|string $value)
 * @method $this unsCanUseDefaultValue()
 * @method $this unsCanUseWebsiteValue()
 * @method $this unsScope()
 */
abstract class AbstractElement extends AbstractForm
{
    protected $_id;
    protected $_type;
    protected $_form;
    protected $_elements;

    /**
     * @var RendererInterface
     */
    protected $_renderer;

    /**
     * AbstractElement constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->_renderer = Form::getElementRenderer();
    }

    /**
     * Add form element
     *
     * @param string|false $after
     * @return  $this
     */
    #[\Override]
    public function addElement(AbstractElement $element, $after = false)
    {
        if ($this->getForm()) {
            $this->getForm()->checkElementId($element->getId());
            $this->getForm()->addElementToCollection($element);
        }

        parent::addElement($element, $after);
        return $this;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return Form|AbstractForm
     */
    public function getForm()
    {
        return $this->_form;
    }

    /**
     * @param string $id
     * @return $this
     */
    #[\Override]
    public function setId($id)
    {
        $this->_id = $id;
        $this->setData('html_id', $id);
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlId()
    {
        return $this->getForm()->getHtmlIdPrefix() . $this->getData('html_id') . $this->getForm()->getHtmlIdSuffix();
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = $this->getData('name');
        if ($suffix = $this->getForm()->getFieldNameSuffix()) {
            $name = $this->getForm()->addSuffixToName($name, $suffix);
        }
        return $name;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;
        $this->setData('type', $type);
        return $this;
    }

    /**
     * @param AbstractForm $form
     * @return $this
     */
    public function setForm($form)
    {
        $this->_form = $form;
        return $this;
    }

    #[\Override]
    public function removeField($elementId)
    {
        $this->getForm()->removeField($elementId);
        return parent::removeField($elementId);
    }

    /**
     * @return array
     */
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'onclick', 'onchange', 'disabled', 'readonly', 'tabindex', 'autocomplete'];
    }

    /**
     * @param string $class
     * @return $this
     */
    public function addClass($class)
    {
        $oldClass = $this->getClass();
        $this->setClass($oldClass . ' ' . $class);
        return $this;
    }

    /**
     * Remove CSS class
     *
     * @param string $class
     * @return $this
     */
    public function removeClass($class)
    {
        $classes = array_unique(explode(' ', $this->getClass()));
        if (false !== ($key = array_search($class, $classes))) {
            unset($classes[$key]);
        }
        $this->setClass(implode(' ', $classes));
        return $this;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function _escape($string)
    {
        return htmlspecialchars((string) $string, ENT_COMPAT);
    }

    /**
     * @param string|null $index
     * @return string
     */
    public function getEscapedValue($index = null)
    {
        $value = $this->getValue($index);

        if ($filter = $this->getValueFilter()) {
            $value = $filter->filter($value);
        }

        // Handle array values (e.g., from delete checkbox)
        if (is_array($value)) {
            return '';
        }

        return $this->_escape((string) $value);
    }

    /**
     * @return $this
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->_renderer = $renderer;
        return $this;
    }

    /**
     * @return RendererInterface
     */
    public function getRenderer()
    {
        return $this->_renderer;
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        $html = '<input id="' . $this->getHtmlId() . '" name="' . $this->getName()
             . '" value="' . $this->getEscapedValue() . '" ' . $this->serialize($this->getHtmlAttributes()) . '/>' . "\n";
        $html .= $this->getAfterElementHtml();
        return $html;
    }

    /**
     * @return string
     */
    public function getAfterElementHtml()
    {
        return $this->getData('after_element_html');
    }

    /**
     * Render HTML for element's label
     *
     * @param string $idSuffix
     * @return string
     */
    public function getLabelHtml($idSuffix = '')
    {
        if (!is_null($this->getLabel())) {
            $html = '<label for="' . $this->getHtmlId() . $idSuffix . '">' . $this->_escape($this->getLabel())
                  . ($this->getRequired() ? ' <span class="required">*</span>' : '') . '</label>' . "\n";
        } else {
            $html = '';
        }
        return $html;
    }

    /**
     * @return string
     */
    public function getDefaultHtml()
    {
        $html = $this->getData('default_html');
        if (is_null($html)) {
            $html = ($this->getNoSpan() === true) ? '' : '<span class="field-row">' . "\n";
            $html .= $this->getLabelHtml();
            $html .= $this->getElementHtml();
            $html .= ($this->getNoSpan() === true) ? '' : '</span>' . "\n";
        }
        return $html;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        if ($this->getRequired()) {
            $this->addClass('required-entry');
        }
        if ($this->_renderer) {
            $html = $this->_renderer->render($this);
        } else {
            $html = $this->getDefaultHtml();
        }
        return $html;
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        return $this->getHtml();
    }

    #[\Override]
    public function serialize($attributes = [], $valueSeparator = '=', $fieldSeparator = ' ', $quote = '"')
    {
        if (in_array('disabled', $attributes) && !empty($this->_data['disabled'])) {
            $this->_data['disabled'] = 'disabled';
        } else {
            unset($this->_data['disabled']);
        }
        if (in_array('checked', $attributes) && !empty($this->_data['checked'])) {
            $this->_data['checked'] = 'checked';
        } else {
            unset($this->_data['checked']);
        }
        return parent::serialize($attributes, $valueSeparator, $fieldSeparator, $quote);
    }

    /**
     * @return bool
     */
    public function getReadonly()
    {
        if ($this->hasData('readonly_disabled')) {
            return $this->_getData('readonly_disabled');
        }

        return $this->_getData('readonly');
    }

    /**
     * @return string
     */
    public function getHtmlContainerId()
    {
        if ($this->hasData('container_id')) {
            return $this->getData('container_id');
        }
        if ($idPrefix = $this->getForm()->getFieldContainerIdPrefix()) {
            return $idPrefix . $this->getId();
        }
        return '';
    }

    /**
     * Add specified values to element values
     *
     * @param string|int|array $values
     * @param bool $overwrite
     * @return $this
     */
    public function addElementValues($values, $overwrite = false)
    {
        if (empty($values) || (is_string($values) && trim($values) == '')) {
            return $this;
        }
        if (!is_array($values)) {
            $values = \Mage::helper('core')->escapeHtml(trim($values));
            $values = [$values => $values];
        }
        $elementValues = $this->getValues();
        if (!empty($elementValues)) {
            foreach ($values as $key => $value) {
                if ((isset($elementValues[$key]) && $overwrite) || !isset($elementValues[$key])) {
                    $elementValues[$key] = \Mage::helper('core')->escapeHtml($value);
                }
            }
            $values = $elementValues;
        }
        $this->setValues($values);

        return $this;
    }
}
