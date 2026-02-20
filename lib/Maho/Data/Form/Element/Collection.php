<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Maho\Data\Form;
use Maho\Data\Form\AbstractForm;

class Collection implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * Elements storage
     *
     * @var array
     */
    private $_elements;

    /**
     * Elements container
     *
     * @var AbstractForm
     */
    private $_container;

    /**
     * Class constructor
     *
     * @param AbstractForm $container
     */
    public function __construct($container)
    {
        $this->_elements = [];
        $this->_container = $container;
    }

    /**
     * Implementation of IteratorAggregate::getIterator()
     *
     * @return ArrayIterator
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->_elements);
    }

    /**
     * Implementation of ArrayAccess:offsetSet()
     *
     * @param mixed $key
     * @param mixed $value
     */
    #[\Override]
    public function offsetSet($key, $value): void
    {
        $this->_elements[$key] = $value;
    }

    /**
     * Implementation of ArrayAccess:offsetGet()
     *
     * @param mixed $key
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetGet($key)
    {
        return $this->_elements[$key];
    }

    /**
     * Implementation of ArrayAccess:offsetUnset()
     *
     * @param mixed $key
     */
    #[\Override]
    public function offsetUnset($key): void
    {
        unset($this->_elements[$key]);
    }

    /**
     * Implementation of ArrayAccess:offsetExists()
     *
     * @param mixed $key
     */
    #[\Override]
    public function offsetExists($key): bool
    {
        return isset($this->_elements[$key]);
    }

    /**
     * Add element to collection
     *
     * @todo get it straight with $after
     * @param string|false $after
     * @return AbstractElement
     */
    public function add(AbstractElement $element, $after = false)
    {
        // Set the Form for the node
        if ($this->_container->getForm() instanceof Form) {
            $element->setContainer($this->_container);
            $element->setForm($this->_container->getForm());
        }

        if ($after === false) {
            $this->_elements[] = $element;
        } elseif ($after === '^') {
            array_unshift($this->_elements, $element);
        } elseif (is_string($after)) {
            $newOrderElements = [];
            foreach ($this->_elements as $index => $currElement) {
                if ($currElement->getId() == $after) {
                    $newOrderElements[] = $currElement;
                    $newOrderElements[] = $element;
                    $this->_elements = array_merge($newOrderElements, array_slice($this->_elements, $index + 1));
                    return $element;
                }
                $newOrderElements[] = $currElement;
            }
            $this->_elements[] = $element;
        }

        return $element;
    }

    /**
     * Sort elements by values using a user-defined comparison function
     *
     * @param mixed $callback
     * @return self
     */
    public function usort($callback)
    {
        usort($this->_elements, $callback);
        return $this;
    }

    /**
     * Remove element from collection
     *
     * @param mixed $elementId
     * @return self
     */
    public function remove($elementId)
    {
        foreach ($this->_elements as $index => $element) {
            if ($elementId == $element->getId()) {
                unset($this->_elements[$index]);
            }
        }
        // Renumber elements for further correct adding and removing other elements
        $this->_elements = array_merge($this->_elements, []);
        return $this;
    }

    /**
     * Count elements in collection
     */
    #[\Override]
    public function count(): int
    {
        return count($this->_elements);
    }

    /**
     * Find element by ID
     *
     * @param mixed $elementId
     * @return AbstractElement|null
     */
    public function searchById($elementId)
    {
        foreach ($this->_elements as $element) {
            if ($element->getId() == $elementId) {
                return $element;
            }
        }
        return null;
    }
}
