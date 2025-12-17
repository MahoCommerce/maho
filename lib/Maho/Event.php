<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho;

use Maho\Event\Observer;
use Maho\Event\Observer\Collection as ObserverCollection;

class Event extends DataObject
{
    /**
     * Observers collection
     *
     * @var ObserverCollection
     */
    protected $_observers;

    /**
     * Constructor
     *
     * Initializes observers collection
     */
    public function __construct(array $data = [])
    {
        $this->_observers = new ObserverCollection();
        parent::__construct($data);
    }

    /**
     * Returns all the registered observers for the event
     *
     * @return ObserverCollection
     */
    public function getObservers()
    {
        return $this->_observers;
    }

    /**
     * Register an observer for the event
     *
     * @return Event
     */
    public function addObserver(Observer $observer)
    {
        $this->getObservers()->addObserver($observer);
        return $this;
    }

    /**
     * Removes an observer by its name
     *
     * @param string $observerName
     * @return Event
     */
    public function removeObserverByName($observerName)
    {
        $this->getObservers()->removeObserverByName($observerName);
        return $this;
    }

    /**
     * Dispatches the event to registered observers
     *
     * @return Event
     */
    public function dispatch()
    {
        $this->getObservers()->dispatch($this);
        return $this;
    }

    /**
     * Retrieve event name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_data['name'] ?? null;
    }

    public function setName(string $data): self
    {
        $this->_data['name'] = $data;
        return $this;
    }

    public function getBlock(): mixed
    {
        return $this->_getData('block');
    }
}
