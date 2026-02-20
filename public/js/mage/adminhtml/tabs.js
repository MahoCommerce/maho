/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienTabs {
    constructor(containerId, destElementId, activeTabId, shadowTabs) {
        this.containerId = containerId;
        this.destElementId = destElementId;
        this.activeTab = null;

        this.tabOnClick = this.tabMouseClick.bind(this);

        this.tabs = document.querySelectorAll(`#${this.containerId} li a.tab-item-link`);

        this.hideAllTabsContent();

        this.tabs.forEach(tab => {
            tab.addEventListener('click', this.tabOnClick);

            // move tab contents to destination element
            const destElement = document.getElementById(this.destElementId);
            if (destElement) {
                const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
                if (tabContentElement && tabContentElement.parentNode.id !== this.destElementId) {
                    destElement.appendChild(tabContentElement);
                    tabContentElement.container = this;
                    tabContentElement.statusBar = tab;
                    tabContentElement.tabObject = tab;
                    tab.contentMoved = true;
                    tab.container = this;
                    tab.show = function() {
                        this.container.showTabContent(this);
                    };
                    varienGlobalEvents?.fireEvent('moveTab', { tab: tab });
                }
            }

            // bind shadow tabs
            if (tab.id && shadowTabs && shadowTabs[tab.id]) {
                tab.shadowTabs = shadowTabs[tab.id];
            }
        });

        this.displayFirst = activeTabId;
        window.addEventListener('load', () => this.moveTabContentInDest());
    }

    setSkipDisplayFirstTab() {
        this.displayFirst = null;
    }

    moveTabContentInDest() {
        this.tabs.forEach(tab => {
            const destElement = document.getElementById(this.destElementId);
            if (destElement && !tab.contentMoved) {
                const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
                if (tabContentElement && tabContentElement.parentNode.id !== this.destElementId) {
                    destElement.appendChild(tabContentElement);
                    tabContentElement.container = this;
                    tabContentElement.statusBar = tab;
                    tabContentElement.tabObject = tab;
                    tab.container = this;
                    tab.show = function() {
                        this.container.showTabContent(this);
                    };
                    varienGlobalEvents?.fireEvent('moveTab', { tab: tab });
                }
            }
        });

        if (this.displayFirst) {
            this.showTabContent(document.getElementById(this.displayFirst));
            this.displayFirst = null;
        }
    }

    getTabContentElementId(tab) {
        if (tab) {
            return tab.id + '_content';
        }
        return false;
    }

    tabMouseClick(event) {
        const tab = event.target.closest('a');

        // go directly to specified url or switch tab
        if ((tab.href.indexOf('#') !== tab.href.length - 1) &&
            !tab.classList.contains('ajax')) {
            location.href = tab.href;
        } else {
            this.showTabContent(tab);
        }
        event.preventDefault();
        event.stopPropagation();
    }

    hideAllTabsContent() {
        this.tabs.forEach(tab => {
            this.hideTabContent(tab);
        });
    }

    // show tab, ready or not
    showTabContentImmediately(tab) {
        this.hideAllTabsContent();
        const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
        if (tabContentElement) {
            tabContentElement.style.display = '';
            tab.classList.add('active');

            // load shadow tabs, if any
            if (tab.shadowTabs && tab.shadowTabs.length) {
                for (const k in tab.shadowTabs) {
                    this.loadShadowTab(document.getElementById(tab.shadowTabs[k]));
                }
            }

            if (!tab.classList.contains('ajax') || !tab.classList.contains('only')) {
                tab.classList.remove('notloaded');
            }

            this.activeTab = tab;
        }
        varienGlobalEvents?.fireEvent('showTab', { tab: tab });
    }

    // the lazy show tab method
    showTabContent(tab) {
        const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
        if (tabContentElement) {
            if (this.activeTab !== tab) {
                const activeTabContentId = this.activeTab ? this.getTabContentElementId(this.activeTab) : null;
                const activeTabContent = activeTabContentId ? document.getElementById(activeTabContentId) : null;
                const result = varienGlobalEvents?.fireEvent('tabChangeBefore', activeTabContent);
                if (result && result.indexOf('cannotchange') !== -1) {
                    return;
                }
            }

            // wait for ajax request, if defined
            const isAjax = tab.classList.contains('ajax');
            const isEmpty = tabContentElement.innerHTML === '' && tab.href.indexOf('#') !== tab.href.length - 1;
            const isNotLoaded = tab.classList.contains('notloaded');

            if (isAjax && (isEmpty || isNotLoaded)) {
                const formData = new FormData();
                formData.append('form_key', window.FORM_KEY || '');

                mahoFetch(tab.href, {
                    method: 'POST',
                    body: formData
                })
                .then(result => {
                    if (typeof result === 'object' && result !== null) {
                        // Response is JSON
                        if (result.error) {
                            alert(result.message);
                        }
                        if (result.ajaxExpired && result.ajaxRedirect) {
                            setLocation(result.ajaxRedirect);
                        }
                    } else {
                        // Response is text/HTML
                        tabContentElement.innerHTML = result;

                        // Execute any scripts in the response
                        const scripts = tabContentElement.querySelectorAll('script');
                        scripts.forEach(script => {
                            const newScript = document.createElement('script');
                            if (script.src) {
                                newScript.src = script.src;
                            } else {
                                newScript.textContent = script.textContent;
                            }
                            document.head.appendChild(newScript);
                            document.head.removeChild(newScript);
                        });

                        this.showTabContentImmediately(tab);
                    }
                })
                .catch(error => {
                    console.error('Tab load error:', error);
                });
            } else {
                this.showTabContentImmediately(tab);
            }
        }
    }

    loadShadowTab(tab) {
        const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
        if (tabContentElement && tab.classList.contains('ajax') && tab.classList.contains('notloaded')) {
            const formData = new FormData();
            formData.append('form_key', window.FORM_KEY || '');

            mahoFetch(tab.href, {
                method: 'POST',
                body: formData
            })
            .then(result => {
                if (typeof result === 'object' && result !== null) {
                    // Response is JSON
                    if (result.error) {
                        alert(result.message);
                    }
                    if (result.ajaxExpired && result.ajaxRedirect) {
                        setLocation(result.ajaxRedirect);
                    }
                } else {
                    // Response is text/HTML
                    tabContentElement.innerHTML = result;

                    // Execute any scripts in the response
                    const scripts = tabContentElement.querySelectorAll('script');
                    scripts.forEach(script => {
                        const newScript = document.createElement('script');
                        if (script.src) {
                            newScript.src = script.src;
                        } else {
                            newScript.textContent = script.textContent;
                        }
                        document.head.appendChild(newScript);
                        document.head.removeChild(newScript);
                    });

                    if (!tab.classList.contains('ajax') || !tab.classList.contains('only')) {
                        tab.classList.remove('notloaded');
                    }
                }
            })
            .catch(error => {
                console.error('Shadow tab load error:', error);
            });
        }
    }

    hideTabContent(tab) {
        const tabContentElement = document.getElementById(this.getTabContentElementId(tab));
        const destElement = document.getElementById(this.destElementId);
        if (destElement && tabContentElement) {
            tabContentElement.style.display = 'none';
            tab.classList.remove('active');
        }
        varienGlobalEvents?.fireEvent('hideTab', { tab: tab });
    }
}
