/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienTabs {
    constructor() {
        this.initialize(...arguments);
    }

    initialize(containerId, destElementId, activeTabId, shadowTabs) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Tabs container with ID ${containerId} not found in DOM`);
        }

        this.destElementId = destElementId;
        this.activeTab = null;
        this.displayFirst = activeTabId;

        this.tabs = this.container.querySelectorAll('li a.tab-item-link');
        this.tabOnClick = this.tabMouseClick.bind(this);

        // bind shadow tabs
        for (const tab of this.tabs) {
            if (tab.id && shadowTabs?.[tab.id]) {
                tab.shadowTabs = shadowTabs[tab.id];
            }
        }

        this.hideAllTabsContent();
        this.moveTabContentInDest();
        this.bindEventListeners();
    }

    bindEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            this.moveTabContentInDest();
        });
        for (const tab of this.tabs) {
            tab.addEventListener('click', this.tabOnClick);
        }
    }

    setSkipDisplayFirstTab() {
        this.displayFirst = null;
    }

    moveTabContentInDest() {
        const destElement = document.getElementById(this.destElementId);
        if (!destElement) {
            return;
        }
        for (const tab of this.tabs) {
            const tabContentElement = this.getTabContentElement(tab);
            if (!tabContentElement || tabContentElement.parentNode === destElement || tab.contentMoved) {
                continue;
            }
            destElement.appendChild(tabContentElement);
            tabContentElement.container = this;
            tabContentElement.statusBar = tab;
            tabContentElement.tabObject = tab;
            tab.contentMoved = true;
            tab.container = this;
            tab.show = () => this.container.showTabContent(tab);
            varienGlobalEvents?.fireEvent('moveTab', { tab });
        }
        if (this.displayFirst) {
            this.showTabContent(document.getElementById(this.displayFirst));
            this.displayFirst = null;
        }
    }

    getTabContentElementId(tab) {
        return tab instanceof HTMLElement ? `${tab.id}_content` : false;
    }

    getTabContentElement(tab) {
        return tab instanceof HTMLElement ? document.getElementById(this.getTabContentElementId(tab)) : false;
    }

    tabMouseClick(event) {
        const tab = event.target;
        event.preventDefault();

        if (!tab.href.endsWith('#') && !tab.classList.contains('ajax')) {
            setLocation(tab.href);
        } else {
            this.showTabContent(tab);
        }
    }

    hideAllTabsContent() {
        for (const tab of this.tabs) {
            this.hideTabContent(tab);
        }
    }

    // show tab, ready or not
    showTabContentImmediately(tab) {
        this.hideAllTabsContent();

        const tabContentElement = this.getTabContentElement(tab);
        if (tabContentElement) {
            toggleVis(tabContentElement, true);
            tab.classList.add('active');

            // Load shadow tabs, if any
            for (const shadowTab of tab.shadowTabs ?? []) {
                this.loadShadowTab(document.getElementById(shadowTab));
            }
            if (!tab.classList.contains('ajax') || !tab.classList.contains('only')) {
                tab.classList.remove('notloaded');
            }
            this.activeTab = tab;
        }
        varienGlobalEvents?.fireEvent('showTab', { tab });
    }

    // the lazy show tab method
    async showTabContent(tab) {
        const tabContentElement = this.getTabContentElement(tab);
        if (!tabContentElement) {
            return;
        }
        if (this.activeTab !== tab) {
            const result = varienGlobalEvents?.fireEvent('tabChangeBefore', this.getTabContentElement(this.activeTab));
            if (result.includes('cannotchange')) {
                return;
            }
        }

        // wait for ajax request, if defined
        const isAjax = tab.classList.contains('ajax');
        const isEmpty = !tabContentElement.innerHTML && !tab.href.endsWith('#');
        const isNotLoaded = tab.classList.contains('notloaded');

        if (isAjax && (isEmpty || isNotLoaded)) {
            try {
                const html = await mahoFetch(tab.href, { method: 'POST' });
                updateElementHtmlAndExecuteScripts(tabContentElement, html);
                this.showTabContentImmediately(tab);
            } catch (error) {
                setMessagesDiv('Failed to load tab content', 'error') // TODO translate
            }
        } else {
            this.showTabContentImmediately(tab);
        }
    }

    async loadShadowTab(tab) {
        const tabContentElement = this.getTabContentElement(tab);
        if (!tabContentElement || !tab.classList.contains('ajax') || !tab.classList.contains('notloaded')) {
            return;
        }

        try {
            const html = await mahoFetch(tab.href, { method: 'POST' });
            updateElementHtmlAndExecuteScripts(tabContentElement, html);
        } catch (error) {
            setMessagesDiv('Failed to load tab content', 'error') // TODO translate
        }
    }

    hideTabContent(tab) {
        const destElement = document.getElementById(this.destElementId);
        const tabContentElement = this.getTabContentElement(tab);
        if (destElement && tabContentElement) {
            toggleVis(tabContentElement, false);
            tab.classList.remove('active');
        }
        varienGlobalEvents?.fireEvent('hideTab', { tab });
    }
};
