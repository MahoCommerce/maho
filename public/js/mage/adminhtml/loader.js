/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class varienLoader {
    constructor() {
        this.callback = false;
        this.cache = new Map();
        this.url = false;
    }

    load(url, params = {}, callback) {
        this.url = url;
        this.callback = callback;

        // Check cache first
        const cachedTransport = this.cache.get(url);
        if (cachedTransport) {
            this.processResult(cachedTransport);
            return;
        }

        if (params.updaterId) {
            new varienUpdater(params.updaterId, url, {
                evalScripts: true,
                onComplete: (transport) => this.processResult(transport),
                onFailure: (transport) => this._processFailure(transport)
            });
        } else {
            const body = params instanceof URLSearchParams ? params.toString() :
                        typeof params === 'object' ? new URLSearchParams(params).toString() :
                        params;

            (async () => {
                try {
                    const result = await mahoFetch(url, {
                        method: 'POST',
                        body,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const transport = {
                        responseText: typeof result === 'string' ? result : JSON.stringify(result),
                        status: 200,
                        ok: true
                    };

                    this.processResult(transport);

                } catch (error) {
                    const transport = {
                        responseText: error.message || '',
                        status: error.status || 500,
                        ok: false,
                        error
                    };

                    this._processFailure(transport);
                }
            })();
        }
    }

    _processFailure(transport) {
        window.location.href = BASE_URL;
    }

    processResult(transport) {
        this.cache.set(this.url, transport);
        if (this.callback) {
            this.callback(transport.responseText);
        }
    }
}


let loaderTimeout = null;

function showLoader(loaderArea) {
    if (typeof loaderArea === 'string') {
        loaderArea = document.getElementById(loaderArea);
    }
    if (!(loaderArea instanceof Element)) {
        loaderArea = document.body;
    }

    const loadingMask = document.getElementById('loading-mask');
    if (!loadingMask || loadingMask.style.display !== 'none') {
        return;
    }

    // Clone position logic
    const rect = loaderArea.getBoundingClientRect();
    loadingMask.style.position = 'absolute';
    loadingMask.style.left = (rect.left - 2) + 'px';
    loadingMask.style.top = rect.top + 'px';
    loadingMask.style.width = rect.width + 'px';
    loadingMask.style.height = rect.height + 'px';

    loadingMask.style.display = 'block';

    // Hide child elements initially
    Array.from(loadingMask.children).forEach(child => {
        child.style.display = 'none';
    });

    loaderTimeout = setTimeout(() => {
        Array.from(loadingMask.children).forEach(child => {
            child.style.display = '';
        });
    }, typeof window.LOADING_TIMEOUT === 'undefined' ? 200 : window.LOADING_TIMEOUT);
}

function hideLoader() {
    const loadingMask = document.getElementById('loading-mask');
    if (loadingMask) {
        loadingMask.style.display = 'none';
    }
    if (loaderTimeout) {
        clearTimeout(loaderTimeout);
        loaderTimeout = null;
    }
}

class varienUpdater {
    constructor(containerId, url, options = {}) {
        this.container = document.getElementById(containerId);
        this.url = url;
        this.options = options;

        if (!this.container) {
            console.error(`Container element '${containerId}' not found`);
            return;
        }

        this.load();
    }

    async load() {
        try {
            const result = await mahoFetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const transport = {
                responseText: typeof result === 'string' ? result : JSON.stringify(result),
                status: 200,
                ok: true
            };

            this.updateContent(transport);

            if (this.options.onComplete) {
                this.options.onComplete(transport);
            }

        } catch (error) {
            const transport = {
                responseText: error.message || '',
                status: error.status || 500,
                ok: false,
                error
            };

            if (this.options.onFailure) {
                this.options.onFailure(transport);
            }
        }
    }

    updateContent(transport) {
        this.container.innerHTML = transport.responseText;
        if (this.options.evalScripts) {
            this._evalScripts();
        }
    }

    _evalScripts() {
        const scripts = this.container.querySelectorAll('script');
        scripts.forEach(script => {
            if (script.src) {
                const newScript = document.createElement('script');
                newScript.src = script.src;
                document.head.appendChild(newScript);
            } else if (script.textContent) {
                eval(script.textContent);
            }
        });
    }
}
