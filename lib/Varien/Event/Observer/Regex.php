<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Event
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Event regex observer object
 *
 * @category   Varien
 * @package    Varien_Event
 *
 * @method string getEventRegex()
 */
class Varien_Event_Observer_Regex extends Varien_Event_Observer
{
    /**
     * Checkes the observer's event_regex against event's name
     *
     * @return boolean
     */
    #[\Override]
    public function isValidFor(Varien_Event $event)
    {
        return preg_match($this->getEventRegex(), $event->getName());
    }
}
