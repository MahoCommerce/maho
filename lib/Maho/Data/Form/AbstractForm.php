<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form;

use Maho\Data\Form\Element\AbstractElement;
use Maho\Data\Form\Element\Collection;
use Maho\DataObject;

/**
 * Abstract class for form, coumn and fieldset
 *
 * @method \Maho\Data\Form getForm()
 * @method bool getUseContainer()
 * @method $this setAction(string $value)
 * @method $this setMethod(string $value)
 * @method $this setName(string $value)
 * @method $this setValue(mixed $value)
 * @method $this setUseContainer(bool $value)
 * @method $this setDisabled(bool $value)
 * @method $this setRequired(bool $value)
 */
abstract class AbstractForm extends \Maho\DataObject
{
    /**
     * Form level elements collection
     *
     * @var Collection
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
     * @param boolean $readonly
     * @param boolean $useDisabled
     * @return $this
     */
    public function setReadonly($readonly, $useDisabled = false)
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
     * @param string $elementId
     * @return $this
     */
    public function removeField($elementId)
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
}
