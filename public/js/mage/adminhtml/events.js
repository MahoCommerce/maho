/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienEvents {
    constructor() {
        this.arrEvents = {};
        this.eventPrefix = '';
    }

    /**
     * Attaches a {handler} function to the publisher's {eventName} event for execution upon the event firing
     * @param {string} eventName
     * @param {Function} handler
     * @param {boolean} asynchFlag [optional] Defaults to false if omitted. Indicates whether to execute {handler} asynchronously (true) or not (false).
     */
    attachEventHandler(eventName, handler, asynchFlag = false) {
        if (typeof handler === 'undefined' || handler === null) {
            return;
        }

        eventName = eventName + this.eventPrefix;

        // using an event cache array to track all handlers for proper cleanup
        if (!this.arrEvents[eventName]) {
            this.arrEvents[eventName] = [];
        }

        // create a custom object containing the handler method and the asynch flag
        const handlerObj = {
            method: handler,
            asynch: asynchFlag
        };

        this.arrEvents[eventName].push(handlerObj);
    }

    /**
     * Removes a single handler from a specific event
     * @param {string} eventName The event name to clear the handler from
     * @param {Function} handler A reference to the handler function to un-register from the event
     */
    removeEventHandler(eventName, handler) {
        eventName = eventName + this.eventPrefix;

        if (this.arrEvents[eventName]) {
            this.arrEvents[eventName] = this.arrEvents[eventName].filter(obj => obj.method !== handler);
        }
    }

    /**
     * Removes all handlers from a single event
     * @param {string} eventName The event name to clear handlers from
     */
    clearEventHandlers(eventName) {
        eventName = eventName + this.eventPrefix;
        this.arrEvents[eventName] = null;
    }

    /**
     * Removes all handlers from ALL events
     */
    clearAllEventHandlers() {
        this.arrEvents = {};
    }

    /**
     * Fires the event {eventName}, resulting in all registered handlers to be executed.
     * It also collects and returns results of all non-asynchronous handlers
     * @param {string} eventName The name of the event to fire
     * @param {Object} args [optional] Any object, will be passed into the handler function as the only argument
     * @return {Array}
     */
    fireEvent(eventName, args) {
        const evtName = eventName + this.eventPrefix;
        const results = [];

        if (!this.arrEvents[evtName]) {
            return results;
        }

        const len = this.arrEvents[evtName].length;

        for (let i = 0; i < len; i++) {
            try {
                let result;
                const handler = this.arrEvents[evtName][i];

                if (!handler || !handler.method) {
                    continue;
                }

                if (handler.asynch) {
                    if (arguments.length > 1) {
                        setTimeout(() => {
                            handler.method.call(this, args);
                        }, 10);
                    } else {
                        setTimeout(() => {
                            handler.method.call(this);
                        }, 1);
                    }
                } else {
                    if (arguments.length > 1) {
                        result = handler.method.call(this, args);
                    } else {
                        result = handler.method.call(this);
                    }
                    results.push(result);
                }
            } catch (e) {
                const objectId = this.id || '[unknown object]';
                alert(`error: error in ${objectId}.fireEvent():\n\nevent name: ${eventName}\n\nerror message: ${e.message}`);
            }
        }

        return results;
    }
}

const varienGlobalEvents = new varienEvents();

