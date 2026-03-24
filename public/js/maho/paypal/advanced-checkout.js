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
        this.sdkUrl = formDiv.dataset.sdkUrl;
        this.clientTokenUrl = formDiv.dataset.clientTokenUrl;
        this.currencyCode = formDiv.dataset.currencyCode;
        this.methodCode = formDiv.dataset.methodCode;
        this._cardSession = null;
        this._mounted = false;
    }

    async loadSdkAndMount() {
        if (this._mounted) return;

        try {
            showLoader(this.formDiv);
            const sdk = await MahoPaypalSdk.init(this.sdkUrl, this.clientTokenUrl);
            await this._renderFields(sdk);
        } catch (err) {
            hideLoader();
            this.handleError(err);
        }
    }

    async _renderFields(sdk) {
        if (this._mounted) return;
        if (!document.body.contains(this.formDiv)) return;

        const paymentMethods = await sdk.findEligibleMethods({ currencyCode: this.currencyCode });
        if (!paymentMethods.isEligible('advanced_cards')) {
            const errorDiv = document.getElementById('paypal-card-errors');
            if (errorDiv) {
                errorDiv.textContent = 'Card payments are not available at this time. Please choose another payment method.';
                errorDiv.style.display = 'block';
            }
            return;
        }

        this._cardSession = sdk.createCardFieldsOneTimePaymentSession();

        const numberField = this._cardSession.createCardFieldsComponent({
            type: 'number',
            placeholder: 'Card number',
        });
        const expiryField = this._cardSession.createCardFieldsComponent({
            type: 'expiry',
            placeholder: 'MM/YY',
        });
        const cvvField = this._cardSession.createCardFieldsComponent({
            type: 'cvv',
            placeholder: 'CVV',
        });

        const numberContainer = document.querySelector('#paypal-card-fields-number');
        const expiryContainer = document.querySelector('#paypal-card-fields-expiry');
        const cvvContainer = document.querySelector('#paypal-card-fields-cvv');

        if (numberContainer && expiryContainer && cvvContainer) {
            numberContainer.replaceChildren(numberField);
            expiryContainer.replaceChildren(expiryField);
            cvvContainer.replaceChildren(cvvField);
            this._mounted = true;
            await new Promise(r => setTimeout(r, 500));
            hideLoader();
        }
    }

    async submitCard() {
        if (!this._cardSession) {
            return false;
        }

        try {
            const saveVault = this.formDiv.querySelector('#paypal_advanced_save_vault')?.checked || false;
            const response = await mahoFetch(this.createOrderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ method: this.methodCode, save_vault: saveVault }),
                loaderArea: this.formDiv,
            });

            if (!response.success || !response.paypal_order_id) {
                throw new Error(response.message || 'Failed to create PayPal order');
            }

            const submitOptions = {};
            if (response.billing_address) {
                submitOptions.billingAddress = response.billing_address;
            }

            const submitResult = await this._cardSession.submit(response.paypal_order_id, submitOptions);

            if (submitResult.state === 'canceled') {
                return false;
            }

            if (submitResult.state === 'failed') {
                const detail = submitResult.error?.message || submitResult.message || '';
                throw new Error(detail || 'Card payment failed. Please try again.');
            }

            if (submitResult.state === 'succeeded') {
                await this.onApprove({ orderId: response.paypal_order_id });
                return true;
            }

            return false;
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
                paypal_order_id: data.orderId,
                method: this.methodCode,
            }),
            loaderArea: this.formDiv,
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
    if (formDiv.dataset.methodCode !== 'paypal_advanced_checkout') return;

    // Always re-create on new DOM elements (checkout may re-render payment HTML)
    const checkout = new MahoPaypalAdvancedCheckout(formDiv);
    formDiv._paypalAdvanced = checkout;
    checkout.loadSdkAndMount();
}, true);

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-checkout');
    if (!btn) return;
    if (typeof payment === 'undefined' || payment.currentMethod !== 'paypal_advanced_checkout') return;

    const formDiv = document.getElementById('payment_form_paypal_advanced_checkout');
    if (!formDiv || !formDiv._paypalAdvanced) return;

    e.preventDefault();
    e.stopImmediatePropagation();
    formDiv._paypalAdvanced.submitCard();
}, true);
