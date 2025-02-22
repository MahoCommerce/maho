<?php

/**
 * Maho
 *
 * @package    Varien_Event
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Event object and dispatcher
 *
 * @package    Varien_Event
 */
class Varien_Event extends Varien_Object
{
    /**
     * Observers collection
     *
     * @var Varien_Event_Observer_Collection
     */
    protected $_observers;

    /**
     * Array containing arguments from `config.xml`, or an empty array if the `<args>` node is missing or empty.
     *
     * This example defines an arg `is_ajax=1` for the `controller_action_predispatch` event:
     * ```xml
     * <global>
     *     <events>
     *         <controller_action_predispatch>
     *             <observers>
     *                 <my_event_observer>
     *                     <class>Company_Name_Model_Observer</class>
     *                     <method>process</method>
     *                     <args>
     *                         <is_ajax>1</is_ajax>
     *                     </args>
     *                 </my_event_observer>
     *             </observers>
     *         </controller_action_predispatch>
     *     </events>
     * </global>
     * ```
     *
     * In the observer method, `Company_Name_Model_Observer->process()`, access the args with:
     * ```php
     * public function process(Varien_Event_Observer $observer): void
     * {
     *     $isAjax = (bool) $observer->getEvent()->args['is_ajax'];
     *     // ...
     * }
     * ```
     */
    public array $args;

    /**
     * Constructor
     *
     * Initializes observers collection
     */
    public function __construct(array $data = [])
    {
        $this->_observers = new Varien_Event_Observer_Collection();
        parent::__construct($data);
    }

    /**
     * Returns all the registered observers for the event
     *
     * @return Varien_Event_Observer_Collection
     */
    public function getObservers()
    {
        return $this->_observers;
    }

    /**
     * Register an observer for the event
     *
     * @return Varien_Event
     */
    public function addObserver(Varien_Event_Observer $observer)
    {
        $this->getObservers()->addObserver($observer);
        return $this;
    }

    /**
     * Removes an observer by its name
     *
     * @param string $observerName
     * @return Varien_Event
     */
    public function removeObserverByName($observerName)
    {
        $this->getObservers()->removeObserverByName($observerName);
        return $this;
    }

    /**
     * Dispatches the event to registered observers
     *
     * @return Varien_Event
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
        return isset($this->_data['name']) ? $this->_data['name'] : null;
    }

    public function setName($data)
    {
        $this->_data['name'] = $data;
        return $this;
    }

    public function getBlock()
    {
        return $this->_getData('block');
    }
}
