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
        this.containerId = formDiv.dataset.containerId;
        this.methodCode = formDiv.dataset.methodCode;
        this.sdkUrl = formDiv.dataset.sdkUrl;
        this.sdkNamespace = formDiv.dataset.sdkNamespace;
        this._mounted = false;
    }

    loadSdkAndMount() {
        if (this._mounted) return;

        if (window[this.sdkNamespace]) {
            this._renderButtons();
            return;
        }

        showLoader(this.formDiv);
        const script = document.createElement('script');
        script.src = this.sdkUrl;
        script.dataset.namespace = this.sdkNamespace;
        script.onload = () => {
            hideLoader();
            this._renderButtons();
        };
        script.onerror = () => hideLoader();
        document.head.appendChild(script);
    }

    _renderButtons() {
        if (this._mounted) return;
        this._mounted = true;

        const sdk = window[this.sdkNamespace];
        if (!sdk) {
            console.error('PayPal JS SDK not loaded');
            return;
        }

        sdk.Buttons({
            createOrder: () => this.createOrder(),
            onApprove: (data) => this.onApprove(data),
            onError: (err) => this.handleError(err),
            onCancel: () => {},
        }).render('#' + this.containerId);
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
                paypal_order_id: data.orderID,
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
    if (!formDiv.dataset.sdkUrl || formDiv._paypalCheckout) return;

    const checkout = new MahoPaypalStandardCheckout(formDiv);
    formDiv._paypalCheckout = checkout;
    checkout.loadSdkAndMount();
}, true);

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paypal-shortcut-button[data-sdk-url]').forEach(function(el) {
        const checkout = new MahoPaypalStandardCheckout(el);
        el._paypalCheckout = checkout;
        checkout.loadSdkAndMount();
    });
});
