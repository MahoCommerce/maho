<?php

/**
 * Maho
 *
 * @package    Varien_Event
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Event_Observer_Collection
{
    /**
     * Array of observers
     *
     * @var array
     */
    protected $_observers;

    /**
     * Initializes observers
     *
     */
    public function __construct()
    {
        $this->_observers = [];
    }

    /**
     * Returns all observers in the collection
     *
     * @return array
     */
    public function getAllObservers()
    {
        return $this->_observers;
    }

    /**
     * Returns observer by its name
     *
     * @param string $observerName
     * @return Varien_Event_Observer
     */
    public function getObserverByName($observerName)
    {
        return $this->_observers[$observerName];
    }

    /**
     * Adds an observer to the collection
     *
     * @return $this
     */
    public function addObserver(Varien_Event_Observer $observer)
    {
        $this->_observers[$observer->getName()] = $observer;
        return $this;
    }

    /**
     * Removes an observer from the collection by its name
     *
     * @param string $observerName
     * @return $this
     */
    public function removeObserverByName($observerName)
    {
        unset($this->_observers[$observerName]);
        return $this;
    }

    /**
     * Dispatches an event to all observers in the collection
     *
     * @return $this
     */
    public function dispatch(Varien_Event $event)
    {
        foreach ($this->_observers as $observer) {
            $observer->dispatch($event);
        }
        return $this;
    }
}
