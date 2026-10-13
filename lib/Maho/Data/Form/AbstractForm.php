<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data\Form;

use Maho\Data\Form\Element\AbstractElement;
use Maho\Data\Form\Element\Collection;
use Maho\DataObject;

/**
 * Abstract class for form, coumn and fieldset
 *
 * @method \Maho\Data\Form getForm()
 * @method $this setValue(mixed $value)
 */
abstract class AbstractForm extends \Maho\DataObject
{
    /**
     * Form level elements collection
     *
     * @var Collection|null
     */
    protected $_elements;

    /**
     * Element type classes
     *
     * @var array
     */
    protected $_types = [];

    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * @param string $type
     * @param string $className
     * @return $this
     */
    public function addType($type, $className)
    {
        $this->_types[$type] = $className;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getElements()
    {
        if (empty($this->_elements)) {
            $this->_elements = new Collection($this);
        }
        return $this->_elements;
    }

    /**
     * Disable elements
     *
     * @return $this
     */
    public function setReadonly(bool $readonly, bool $useDisabled = false)
    {
        if ($useDisabled) {
            $this->setDisabled($readonly);
            $this->setData('readonly_disabled', $readonly);
        } else {
            $this->setData('readonly', $readonly);
        }
        foreach ($this->getElements() as $element) {
            $element->setReadonly($readonly, $useDisabled);
        }

        return $this;
    }

    /**
     * Add form element
     *
     * @param string|false $after
     * @return $this
     */
    public function addElement(AbstractElement $element, $after = null)
    {
        $element->setForm($this);
        $this->getElements()->add($element, $after);
        return $this;
    }

    /**
     * Add child element
     *
     * if $after parameter is false - then element adds to end of collection
     * if $after parameter is null - then element adds to befin of collection
     * if $after parameter is string - then element adds after of the element with some id
     *
     * @param   string $elementId
     * @param   string $type
     * @param   array  $config
     * @param   mixed  $after
     * @return AbstractElement
     */
    public function addField($elementId, $type, $config, $after = false)
    {
        if (isset($this->_types[$type])) {
            $className = $this->_types[$type];
        } else {
            // Convert type to PascalCase (e.g., 'text_configurable' -> 'TextConfigurable')
            $typePascalCase = str_replace('_', '', ucwords($type, '_'));
            $className = '\\Maho\\Data\\Form\\Element\\' . $typePascalCase;
        }

        if (class_exists($className)) {
            $element = new $className($config);
        } else {
            $className = \Maho\Data\Form\Element\Note::class;
            $element = new $className($config);
        }
        $element->setId($elementId);
        $this->addElement($element, $after);
        return $element;
    }

    /**
     * @return $this
     */
    public function removeField(?string $elementId): self
    {
        $this->getElements()->remove($elementId);
        return $this;
    }

    /**
     * @param string $elementId
     * @param array $config
     * @param bool|string|null $after
     *
     * @return Element\Fieldset
     */
    public function addFieldset($elementId, $config, $after = false)
    {
        $element = new Element\Fieldset($config);
        $element->setId($elementId);
        $this->addElement($element, $after);
        return $element;
    }

    /**
     * @param string $elementId
     * @param array $config
     * @return Element\Column
     */
    public function addColumn($elementId, $config)
    {
        $element = new Element\Column($config);
        $element->setForm($this)
            ->setId($elementId);
        $this->addElement($element);
        return $element;
    }

    /**
     * @return array
     */
    #[\Override]
    public function __toArray(array $arrAttributes = [])
    {
        $res = [];
        $res['config']  = $this->getData();
        $res['formElements'] = [];
        foreach ($this->getElements() as $element) {
            $res['formElements'][] = $element->toArray();
        }
        return $res;
    }

    public function setAction(?string $value): static
    {
        return $this->setData('action', $value);
    }

    public function setDisabled(?bool $value = true): static
    {
        return $this->setData('disabled', $value);
    }

    public function setMethod(?string $value): static
    {
        return $this->setData('method', $value);
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function setRequired(?bool $value = true): static
    {
        return $this->setData('required', $value);
    }

    public function getUseContainer(): ?bool
    {
        $value = $this->getData('use_container');
        return $value === null ? null : (bool) $value;
    }

    public function setUseContainer(?bool $value = true): static
    {
        return $this->setData('use_container', $value);
    }
}
