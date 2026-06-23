// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

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
        // Review mode (multistep checkout): PayPal approval only pre-confirms the funding
        // source. The native "Place Order" button stays the terminal action and runs
        // placeApprovedOrder() (capture + placement). onApprove does not redirect here.
        this.reviewMode = formDiv.dataset.reviewMode === '1';
        this._approvedOrderId = null;
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
            hideLoader();
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
            if (this.reviewMode && !this._validateAgreements()) {
                return;
            }
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

        const saveVault = this.formDiv.querySelector('input[name="payment[save_vault]"]')?.checked || false;
        const response = await mahoFetch(this.createOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ method: this.methodCode, save_vault: saveVault }),
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
        // Review mode: don't capture/place or redirect here. Record the approved order id
        // and enable the native "Place Order" button, which becomes the terminal action.
        if (this.reviewMode) {
            this._approvedOrderId = data.orderId;
            const hidden = document.getElementById('paypal_review_order_id');
            if (hidden) {
                hidden.value = data.orderId;
            }
            document.querySelectorAll('#checkout-review-submit .btn-checkout').forEach((btn) => {
                btn.disabled = false;
                btn.removeAttribute('disabled');
            });
            this.formDiv.classList.add('paypal-review-approved');
            return;
        }

        await this._captureAndRedirect(data.orderId);
    }

    /**
     * Terminal action for review mode: capture + place the order using the previously
     * approved PayPal order id, then redirect to success.
     */
    async placeApprovedOrder() {
        if (!this._validateAgreements()) {
            return;
        }
        const orderId = this._approvedOrderId
            || document.getElementById('paypal_review_order_id')?.value;
        if (!orderId) {
            this.handleError(new Error('Please confirm your payment with PayPal first.'));
            return;
        }
        await this._captureAndRedirect(orderId);
    }

    async _captureAndRedirect(orderId) {
        const response = await mahoFetch(this.approveOrderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paypal_order_id: orderId,
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

    _validateAgreements() {
        const boxes = document.querySelectorAll('.checkout-agreements input[type="checkbox"]');
        for (const box of boxes) {
            if (!box.checked) {
                this.handleError(new Error('Please agree to all the terms and conditions before placing the order.'));
                return false;
            }
        }
        return true;
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

    // In multistep checkout the smart button and order placement move to the review step:
    // the payment step only shows a hint, the native review button stays visible, and the
    // `paypal-standard-active` body class (which hides .btn-checkout) must NOT apply. Only
    // onestep (payment + review on one page) keeps the button here as the terminal action.
    const isOnestep = !!document.getElementById('onestep-checkout');
    document.body.classList.toggle('paypal-standard-active', isPaypalStandard && isOnestep);

    if (!isPaypalStandard || !isOnestep) return;

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
