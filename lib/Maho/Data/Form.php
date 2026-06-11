<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data;

use Maho\Data\Form\AbstractForm;
use Maho\Data\Form\Element\Collection as ElementCollection;
use Maho\Data\Form\Element\Renderer\RendererInterface;
use Maho\Profiler;

/**
 * @method string getHtmlIdPrefix()
 * @method $this setHtmlIdPrefix(string $value)
 * @method string getHtmlIdSuffix()
 * @method string getFieldNameSuffix()
 * @method setDataObject(\Mage_Core_Model_Abstract $value)
 * @method $this setFieldNameSuffix(string $value)
 */
class Form extends AbstractForm
{
    /**
     * All form elements collection
     *
     * @var ElementCollection
     */
    protected $_allElements;

    /**
     * form elements index
     *
     * @var array
     */
    protected $_elementsIndex;

    protected static $_defaultElementRenderer;
    protected static $_defaultFieldsetRenderer;
    protected static $_defaultFieldsetElementRenderer;

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->_allElements = new ElementCollection($this);
    }

    public static function setElementRenderer(RendererInterface $renderer): void
    {
        self::$_defaultElementRenderer = $renderer;
    }

    public static function setFieldsetRenderer(RendererInterface $renderer): void
    {
        self::$_defaultFieldsetRenderer = $renderer;
    }

    public static function setFieldsetElementRenderer(RendererInterface $renderer): void
    {
        self::$_defaultFieldsetElementRenderer = $renderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getElementRenderer()
    {
        return self::$_defaultElementRenderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getFieldsetRenderer()
    {
        return self::$_defaultFieldsetRenderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getFieldsetElementRenderer()
    {
        return self::$_defaultFieldsetElementRenderer;
    }

    /**
     * Return allowed HTML form attributes
     * @return array
     */
    public function getHtmlAttributes()
    {
        return ['id', 'name', 'method', 'action', 'enctype', 'class', 'onsubmit'];
    }

    /**
     * Add form element
     *
     * @param string|false $after
     * @return Form
     * @throws \Exception
     */
    #[\Override]
    public function addElement(Form\Element\AbstractElement $element, $after = false)
    {
        $this->checkElementId($element->getId());
        parent::addElement($element, $after);
        $this->addElementToCollection($element);
        return $this;
    }

    /**
     * Check existing element
     */
    protected function _elementIdExists(?string $elementId): bool
    {
        return isset($this->_elementsIndex[(string) $elementId]);
    }

    public function addElementToCollection(Form\Element\AbstractElement $element): self
    {
        $this->_elementsIndex[(string) $element->getId()] = $element;
        $this->_allElements->add($element);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function checkElementId(?string $elementId): bool
    {
        if ($this->_elementIdExists($elementId)) {
            throw new \Exception('Element with id "' . $elementId . '" already exists');
        }
        return true;
    }

    /**
     * @return $this
     */
    public function getForm()
    {
        return $this;
    }

    public function getElement(?string $elementId): ?Form\Element\AbstractElement
    {
        return $this->_elementsIndex[(string) $elementId] ?? null;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function setValues($values)
    {
        foreach ($this->_allElements as $element) {
            if (isset($values[$element->getId()])) {
                $element->setValue($values[$element->getId()]);
            } else {
                $element->setValue(null);
            }
        }
        return $this;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function addValues($values)
    {
        if (!is_array($values)) {
            return $this;
        }
        foreach ($values as $elementId => $value) {
            if ($element = $this->getElement($elementId)) {
                $element->setValue($value);
            }
        }
        return $this;
    }

    /**
     * Add suffix to name of all elements
     *
     * @param string $suffix
     * @return Form
     */
    public function addFieldNameSuffix($suffix)
    {
        foreach ($this->_allElements as $element) {
            $name = $element->getName();
            if ($name) {
                $element->setName($this->addSuffixToName($name, $suffix));
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string $suffix
     * @return string
     */
    public function addSuffixToName($name, $suffix)
    {
        if (!$name) {
            return $suffix;
        }
        $vars = explode('[', $name);
        $newName = $suffix;
        foreach ($vars as $index => $value) {
            $newName .= '[' . $value;
            if ($index == 0) {
                $newName .= ']';
            }
        }
        return $newName;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function removeField(?string $elementId): self
    {
        unset($this->_elementsIndex[(string) $elementId]);
        return $this;
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function setFieldContainerIdPrefix($prefix)
    {
        $this->setData('field_container_id_prefix', $prefix);
        return $this;
    }

    /**
     * @return string
     */
    public function getFieldContainerIdPrefix()
    {
        return $this->getData('field_container_id_prefix');
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        Profiler::start('form/toHtml');
        $html = '';
        if ($useContainer = $this->getUseContainer()) {
            $html .= '<form ' . $this->serialize($this->getHtmlAttributes()) . '>';
            $html .= '<div>';
            if (strtolower((string) $this->getData('method')) == 'post') {
                $html .= '<input name="form_key" type="hidden" value="' . \Mage::getSingleton('core/session')->getFormKey() . '">';
            }
            $html .= '</div>';
        }

        foreach ($this->getElements() as $element) {
            $html .= $element->toHtml();
        }

        if ($useContainer) {
            $html .= '</form>';
        }
        Profiler::stop('form/toHtml');
        return $html;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->toHtml();
    }
}
