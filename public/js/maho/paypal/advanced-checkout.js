/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalAdvancedCheckout {
    constructor(formDiv) {
        this.formDiv = formDiv;
        this.createOrderUrl = formDiv.dataset.createOrderUrl;
        this.approveOrderUrl = formDiv.dataset.approveOrderUrl;
        this.methodCode = formDiv.dataset.methodCode;
        this.sdkUrl = formDiv.dataset.sdkUrl;
        this.sdkNamespace = formDiv.dataset.sdkNamespace;
        this.cardFields = null;
        this._mounted = false;
    }

    loadSdkAndMount() {
        if (this._mounted) return;

        if (window[this.sdkNamespace]) {
            this._renderFields();
            return;
        }

        const script = document.createElement('script');
        script.src = this.sdkUrl;
        script.dataset.namespace = this.sdkNamespace;
        script.onload = () => this._renderFields();
        document.head.appendChild(script);
    }

    _renderFields() {
        if (this._mounted) return;

        const sdk = window[this.sdkNamespace];
        if (!sdk) {
            console.error('PayPal Card Fields SDK not loaded');
            return;
        }

        this.cardFields = sdk.CardFields({
            createOrder: () => this.createOrder(),
            onApprove: (data) => this.onApprove(data),
            onError: (err) => this.handleError(err),
        });

        if (this.cardFields.isEligible()) {
            this.cardFields.NumberField().render('#paypal-card-number-field');
            this.cardFields.ExpiryField().render('#paypal-card-expiry-field');
            this.cardFields.CVVField().render('#paypal-card-cvv-field');
            this._mounted = true;
        }
    }

    async createOrder() {
        const response = await mahoFetch(this.createOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method: this.methodCode }),
        });

        if (!response.success || !response.paypal_order_id) {
            throw new Error(response.message || 'Failed to create PayPal order');
        }

        return response.paypal_order_id;
    }

    async submitCard() {
        if (!this.cardFields) {
            return false;
        }

        try {
            await this.cardFields.submit();
            return true;
        } catch (err) {
            this.handleError(err);
            return false;
        }
    }

    async onApprove(data) {
        const response = await mahoFetch(this.approveOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paypal_order_id: data.orderID,
                method: this.methodCode,
            }),
        });

        if (response.success && response.redirect_url) {
            window.location.href = response.redirect_url;
        } else {
            this.handleError(new Error(response.message || 'Payment failed'));
        }
    }

    handleError(err) {
        console.error('PayPal Card Fields error:', err);
        const errorDiv = document.getElementById('paypal-card-errors');
        if (errorDiv) {
            errorDiv.textContent = err.message || 'An error occurred with your card payment.';
            errorDiv.style.display = 'block';
        }
    }
}

document.addEventListener('payment-method:switched', function(e) {
    const formDiv = e.target;
    if (formDiv.dataset.sdkNamespace !== 'paypalCardFields' || formDiv._paypalAdvanced) return;

    const checkout = new MahoPaypalAdvancedCheckout(formDiv);
    formDiv._paypalAdvanced = checkout;
    checkout.loadSdkAndMount();

    if (typeof payment !== 'undefined') {
        payment.addBeforeValidateFunc(formDiv.dataset.methodCode, function() {
            return checkout.submitCard();
        });
    }
}, true);
