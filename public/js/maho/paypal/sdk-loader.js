/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalSdk {
    static _instance = null;
    static _initPromise = null;
    static _scriptLoaded = false;

    static async init(sdkUrl, clientTokenUrl) {
        if (this._initPromise) return this._initPromise;
        this._initPromise = this._doInit(sdkUrl, clientTokenUrl);
        return this._initPromise;
    }

    static _loadScript(sdkUrl) {
        if (this._scriptLoaded || window.paypal) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = sdkUrl;
            script.async = true;
            script.onload = () => {
                this._scriptLoaded = true;
                resolve();
            };
            script.onerror = () => reject(new Error('Failed to load PayPal SDK'));
            document.head.appendChild(script);
        });
    }

    static async _doInit(sdkUrl, clientTokenUrl) {
        await this._loadScript(sdkUrl);

        const response = await mahoFetch(clientTokenUrl, { method: 'GET' });
        if (!response.success) {
            throw new Error(response.message || 'Failed to get client token');
        }

        const components = [];
        if (document.querySelector('[data-method-code="paypal_standard_checkout"]')) {
            components.push('paypal-payments');
        }
        if (document.querySelector('[data-method-code="paypal_advanced_checkout"]')) {
            components.push('card-fields');
        }
        if (components.length === 0) {
            components.push('paypal-payments');
        }

        this._instance = await window.paypal.createInstance({
            clientToken: response.client_token,
            components,
            pageType: 'checkout',
        });

        return this._instance;
    }

    static getInstance() {
        return this._instance;
    }
}
