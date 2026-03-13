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
        this._mounted = false;
        this._paymentSession = null;
    }

    async loadSdkAndMount() {
        if (this._mounted) return;

        try {
            showLoader(this.formDiv);
            const sdk = await MahoPaypalSdk.init(this.sdkUrl, this.clientTokenUrl);
            hideLoader();
            await this._renderButton(sdk);
        } catch (err) {
            hideLoader();
            this.handleError(err);
        }
    }

    async _renderButton(sdk) {
        if (this._mounted) return;

        const paymentMethods = await sdk.findEligibleMethods({ currencyCode: this.currencyCode });
        if (!paymentMethods.isEligible('paypal')) {
            console.warn('PayPal is not eligible for this transaction');
            return;
        }

        this._paymentSession = sdk.createPayPalOneTimePaymentSession({
            onApprove: (data) => this.onApprove(data),
            onCancel: () => {},
            onError: (err) => this.handleError(err),
        });

        const container = document.getElementById(this.containerId);
        if (!container) return;

        const button = container.querySelector('paypal-button');
        if (!button) return;

        button.removeAttribute('hidden');
        button.addEventListener('click', () => {
            this._paymentSession.start(
                { presentationMode: 'auto' },
                this.createOrder().then(orderId => ({ orderId })),
            );
        });

        this._mounted = true;
    }

    async createOrder() {
        const response = await mahoFetch(this.createOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method: this.methodCode }),
            loaderArea: this.formDiv,
        });

        if (!response.success || !response.paypal_order_id) {
            throw new Error(response.message || 'Failed to create PayPal order');
        }

        return response.paypal_order_id;
    }

    async onApprove(data) {
        const response = await mahoFetch(this.approveOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paypal_order_id: data.orderId,
                method: this.methodCode,
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
    }
}

document.addEventListener('payment-method:switched', function(e) {
    const formDiv = e.target;
    if (formDiv.dataset.methodCode !== 'paypal_standard_checkout' || formDiv._paypalCheckout) return;

    const checkout = new MahoPaypalStandardCheckout(formDiv);
    formDiv._paypalCheckout = checkout;
    checkout.loadSdkAndMount();
}, true);

document.addEventListener('payment-method:switched', function(e) {
    const isPaypalStandard = e.target.dataset.methodCode === 'paypal_standard_checkout';
    document.body.classList.toggle('paypal-standard-active', isPaypalStandard);
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
