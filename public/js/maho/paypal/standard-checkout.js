/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalStandardCheckout {
    constructor(formDiv) {
        this.formDiv = formDiv;
        this.createOrderUrl = formDiv.dataset.createOrderUrl;
        this.approveOrderUrl = formDiv.dataset.approveOrderUrl;
        this.sdkUrl = formDiv.dataset.sdkUrl;
        this.clientTokenUrl = formDiv.dataset.clientTokenUrl;
        this.currencyCode = formDiv.dataset.currencyCode;
        this.containerId = formDiv.dataset.containerId;
        this.methodCode = formDiv.dataset.methodCode;
        this.addToCartFormId = formDiv.dataset.addToCartForm || null;
        this._mounted = false;
        this._paymentSession = null;
    }

    async loadSdkAndMount() {
        if (this._mounted) return;

        const container = document.getElementById(this.containerId);
        showLoader(container || this.formDiv);

        try {
            const sdk = await MahoPaypalSdk.init(this.sdkUrl, this.clientTokenUrl);
            await this._renderButton(sdk);
        } catch (err) {
            hideLoader();
            this.handleError(err);
        }
    }

    async _renderButton(sdk) {
        if (this._mounted) return;

        // Re-check DOM — the form may have been replaced by checkout re-render
        if (!document.body.contains(this.formDiv)) return;

        const paymentMethods = await sdk.findEligibleMethods({ currencyCode: this.currencyCode });
        if (!paymentMethods.isEligible('paypal')) {
            console.warn('PayPal is not eligible for this transaction');
            return;
        }

        this._sdk = sdk;
        this._createPaymentSession();

        const container = document.getElementById(this.containerId);
        if (!container) return;

        const button = container.querySelector('paypal-button');
        if (!button) return;

        button.removeAttribute('hidden');
        await new Promise(r => setTimeout(r, 500));
        hideLoader();
        button.addEventListener('click', async () => {
            try {
                const orderId = await this.createOrder();
                this._paymentSession.start(
                    { presentationMode: 'popup' },
                    Promise.resolve({ orderId }),
                );
            } catch (err) {
                this.handleError(err);
                this._createPaymentSession();
            }
        });

        this._mounted = true;
    }

    _createPaymentSession() {
        this._paymentSession = this._sdk.createPayPalOneTimePaymentSession({
            onApprove: (data) => this.onApprove(data),
            onCancel: () => {},
            onError: (err) => this.handleError(err),
        });
    }

    async createOrder() {
        if (this.addToCartFormId) {
            await this._addToCart();
        }

        const saveVault = this.formDiv.querySelector('#paypal_standard_save_vault')?.checked || false;
        const response = await mahoFetch(this.createOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method: this.methodCode, save_vault: saveVault, form_key: FORM_KEY }),
            loaderArea: this.formDiv,
        });

        if (!response.success || !response.paypal_order_id) {
            throw new Error(response.message || 'Failed to create PayPal order');
        }

        return response.paypal_order_id;
    }

    async _addToCart() {
        const form = document.getElementById(this.addToCartFormId);
        if (!form) throw new Error('Add to cart form not found');

        const response = await mahoFetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            loaderArea: false,
        });

        if (response?.error) {
            throw new Error(response.message || 'Failed to add product to cart');
        }
    }

    async onApprove(data) {
        const response = await mahoFetch(this.approveOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paypal_order_id: data.orderId,
                method: this.methodCode,
                form_key: FORM_KEY,
            }),
            loaderArea: this.formDiv,
        });

        if (response.success && response.redirect_url) {
            window.location.href = response.redirect_url;
        } else {
            this.handleError(new Error(response.message || 'Payment approval failed'));
        }
    }

    handleError(err) {
        console.error('PayPal error:', err);
        let errorDiv = this.formDiv.querySelector('.paypal-standard-errors');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'paypal-standard-errors validation-advice';
            this.formDiv.appendChild(errorDiv);
        }
        errorDiv.textContent = err?.message || 'An error occurred with PayPal. Please try again.';
        errorDiv.style.display = '';
    }
}

document.addEventListener('payment-method:switched', function(e) {
    const isPaypalStandard = e.target.dataset.methodCode === 'paypal_standard_checkout';
    document.body.classList.toggle('paypal-standard-active', isPaypalStandard);

    if (!isPaypalStandard) return;

    const formDiv = e.target;
    // Always re-create on new DOM elements (checkout may re-render payment HTML)
    const checkout = new MahoPaypalStandardCheckout(formDiv);
    formDiv._paypalCheckout = checkout;
    checkout.loadSdkAndMount();
}, true);

document.addEventListener('payment-method:switched-off', function(e) {
    if (e.target.dataset.methodCode === 'paypal_standard_checkout') {
        document.body.classList.remove('paypal-standard-active');
    }
}, true);

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paypal-shortcut-button[data-client-token-url]').forEach(function(el) {
        const checkout = new MahoPaypalStandardCheckout(el);
        el._paypalCheckout = checkout;
        checkout.loadSdkAndMount();
    });
});
