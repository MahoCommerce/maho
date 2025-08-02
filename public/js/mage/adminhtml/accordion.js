/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienAccordion {

    cookieName = 'accordion';
    cookieLifetime = 30 * 24 * 60 * 60;

    constructor(containerId, activeOnlyOne) {
        this.containerId = containerId;
        this.container = document.getElementById(this.containerId);
        this.activeOnlyOne = activeOnlyOne || true;
        this.loader = new varienLoader(true);

        this.items = this.container.querySelectorAll(':scope > details');
        this.items.forEach((el) => {
            el.addEventListener('toggle', this.onToggle.bind(this));
        });

        document.addEventListener('DOMContentLoaded', this.initFromCookie.bind(this));
    }

    initFromCookie() {
        try {
            const value = JSON.parse(Cookie.read(this.cookieName) ?? '{}');
            for (const id of value[this.containerId]?.visible ?? []) {
                const item = this.getItemById(id);
                if (item) {
                    this.showItem(item);
                }
            }
        } catch (error) {
            console.error('Failed to read accordion cookie:', error);
        }
    }

    updateCookie() {
        try {
            const value = JSON.parse(Cookie.read(this.cookieName) ?? '{}');

            value[this.containerId] ??= {};
            value[this.containerId].visible = Array.from(this.items).reduce((acc, cur) => {
                if (this.isItemVisible(cur)) {
                    acc.push(cur.id);
                }
                return acc;
            }, []);

            Cookie.write(this.cookieName, JSON.stringify(value), this.cookieLifetime);
        } catch (error) {
            console.error('Failed to write accordion cookie:', error);
        }
    }

    onToggle(event) {
        const item = event.target;

        if (this.isItemVisible(item) && this.activeOnlyOne) {
            this.items.forEach((otherItem) => {
                if (item !== otherItem) {
                    this.hideItem(otherItem);
                }
            });
        }

        this.updateCookie();

        if (item.dataset.url) {
            if (item.dataset.target === 'ajax') {
                this.loadContent(item);
            } else {
                setLocation(item.dataset.url);
            }
        }
    }

    getItemById(itemId) {
        return Array.from(this.items).find((item) => item.id === itemId);
    }

    showItem(item) {
        item.open = true;
    }

    hideItem(item) {
        item.open = false;
    }

    isItemVisible(item) {
        return item.open;
    }

    hideAllItems() {
        this.items.forEach((item) => item.open = false);
    }

    async loadContent(item) {
        const timeoutID = setTimeout(() => item.classList.add('loading'), LOADING_TIMEOUT);
        try {
            const contentsEl = item.querySelector('div[id|=dd]');
            const html = await mahoFetch(item.dataset.url, {
                method: 'POST',
                body: new URLSearchParams({
                    updaterId: contentsEl.id,
                }),
            });
            updateElementHtmlAndExecuteScripts(contentsEl, html);
            item.removeAttribute('data-url');
        } catch (error) {
            console.log(error)
            item.open = false;
        }
        clearTimeout(timeoutID);
        item.classList.remove('loading');
    }
}

/**
 * System > Configuration
 */
const Fieldset = {
    saveUrl: null,

    applyAllCollapse(formId) {
        document.querySelectorAll(`#${formId} details > fieldset`).forEach((container) => {
            Fieldset.applyCollapse(container.id);
        });
    },

    applyCollapse(containerId) {
        const detailsEl = document.getElementById(containerId).closest('details');
        const stateInputEl = document.getElementById(`${containerId}-state`);
        if (!detailsEl || !stateInputEl) {
            return;
        }
        detailsEl.addEventListener('toggle', () => {
            stateInputEl.value = +detailsEl.open;
            Fieldset.saveState(null, { container: containerId, value: stateInputEl.value });
        });
        stateInputEl.value = +detailsEl.open;
    },

    toggleCollapse(containerId) {
        const detailsEl = document.getElementById(containerId).closest('details');
        if (!detailsEl) {
            return;
        }
        detailsEl.open = !detailsEl.open;
    },

    /** @deprecated */
    addToPrefix (value) {
    },

    saveState(url, parameters) {
        url ??= Fieldset.saveUrl;
        if (url) {
            new Ajax.Request(url, {
                method: 'get',
                parameters: Object.toQueryString(parameters),
                loaderArea: false
            });
        }
    },
};
