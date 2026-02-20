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

namespace Maho\Event;

use Maho\Event as MahoEvent;
use Maho\Event\Observer\Collection as ObserverCollection;

class Collection
{
    /**
     * Array of events in the collection
     *
     * @var array
     */
    protected $_events;

    /**
     * Global observers
     *
     * For example regex observers will watch all events that
     *
     * @var ObserverCollection
     */
    protected $_observers;

    /**
     * @var ObserverCollection
     */
    protected $_globalObservers;

    /**
     * Initializes global observers collection
     */
    public function __construct()
    {
        $this->_events = [];
        $this->_globalObservers = new ObserverCollection();
    }

    /**
     * Returns all registered events in collection
     *
     * @return array
     */
    public function getAllEvents()
    {
        return $this->_events;
    }

    /**
     * Returns all registered global observers for the collection of events
     *
     * @return ObserverCollection
     */
    public function getGlobalObservers()
    {
        return $this->_globalObservers;
    }

    /**
     * Returns event by its name
     *
     * If event doesn't exist creates new one and returns it
     *
     * @param string $eventName
     * @return MahoEvent
     */
    public function getEventByName($eventName)
    {
        if (!isset($this->_events[$eventName])) {
            $this->addEvent(new MahoEvent(['name' => $eventName]));
        }
        return $this->_events[$eventName];
    }

    /**
     * Register an event for this collection
     *
     * @return Collection
     */
    public function addEvent(MahoEvent $event)
    {
        $this->_events[$event->getName()] = $event;
        return $this;
    }

    /**
     * Register an observer
     *
     * If observer has event_name property it will be registered for this specific event.
     * If not it will be registered as global observer
     *
     * @return Collection
     */
    public function addObserver(Observer $observer)
    {
        $eventName = $observer->getEventName();
        if ($eventName) {
            $this->getEventByName($eventName)->addObserver($observer);
        } else {
            $this->getGlobalObservers()->addObserver($observer);
        }
        return $this;
    }

    /**
     * Dispatch event name with optional data
     *
     * Will dispatch specific event and will try all global observers
     *
     * @param string $eventName
     * @return Collection
     */
    public function dispatch($eventName, array $data = [])
    {
        $event = $this->getEventByName($eventName);
        $event->addData($data)->dispatch();
        $this->getGlobalObservers()->dispatch($event);
        return $this;
    }
}
