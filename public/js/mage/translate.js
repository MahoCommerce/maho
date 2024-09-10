/**
 * Maho
 *
 * @category    Mage
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Translate {
    constructor(data) {
        this.data = new Map(Object.entries(data));
    }

    translate(text) {
        if(this.data.has(text)) {
            return this.data.get(text);
        }
        return text;
    }

    add(keyOrObject, value) {
        if (arguments.length > 1) {
            this.data.set(keyOrObject, value);
        } else if (typeof keyOrObject == 'object') {
            Object.entries(keyOrObject).forEach(([key, value]) => {
                this.data.set(key, value);
            });
        }
    }
}
