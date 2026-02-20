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

use Maho\Event;
use Maho\Profiler;

class Observer extends \Maho\DataObject
{
    /**
     * Checks the observer's event_regex against event's name
     *
     * @return boolean
     */
    public function isValidFor(Event $event)
    {
        return $this->getEventName() === $event->getName();
    }

    /**
     * Dispatches an event to observer's callback
     *
     * @return $this
     */
    public function dispatch(Event $event)
    {
        if (!$this->isValidFor($event)) {
            return $this;
        }

        $callback = $this->getCallback();
        $this->setEvent($event);

        $profilerKey = 'OBSERVER: ' . (is_object($callback[0]) ? $callback[0]::class : (string) $callback[0]) . ' -> ' . $callback[1];
        Profiler::start($profilerKey);
        call_user_func($callback, $this);
        Profiler::stop($profilerKey);

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * @param string $data
     * @return $this
     */
    public function setName($data)
    {
        return $this->setData('name', $data);
    }

    /**
     * @return string
     */
    public function getEventName()
    {
        return $this->getData('event_name');
    }

    /**
     * @param string $data
     * @return $this
     */
    public function setEventName($data)
    {
        return $this->setData('event_name', $data);
    }

    /**
     * @return array
     */
    public function getCallback()
    {
        return $this->getData('callback');
    }

    /**
     * @param $data
     * @return $this
     */
    public function setCallback($data)
    {
        return $this->setData('callback', $data);
    }

    /**
     * Get observer event object
     *
     * @return Event
     */
    public function getEvent()
    {
        return $this->getData('event');
    }

    /**
     * @param Event $data
     * @return $this
     */
    public function setEvent($data)
    {
        return $this->setData('event', $data);
    }
}
