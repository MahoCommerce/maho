/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalVaultCheckout {
    constructor(formDiv) {
        this.formDiv = formDiv;
        this.createOrderUrl = formDiv.dataset.createOrderUrl;
        this.approveOrderUrl = formDiv.dataset.approveOrderUrl;
        this.methodCode = formDiv.dataset.methodCode;
        this.submitting = false;
    }

    async submit() {
        if (this.submitting) return;

        const tokenSelect = document.getElementById('paypal_vault_token');
        if (!tokenSelect || !tokenSelect.value) {
            alert(Translator.translate('Please select a saved payment method.'));
            return;
        }

        this.submitting = true;
        checkout.setLoadWaiting('review');

        try {
            const createResponse = await mahoFetch(this.createOrderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    method: this.methodCode,
                    vault_token_id: tokenSelect.value,
                    form_key: FORM_KEY,
                }),
            });

            if (!createResponse.success || !createResponse.paypal_order_id) {
                throw new Error(createResponse.message || 'Failed to create order');
            }

            const approveResponse = await mahoFetch(this.approveOrderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    paypal_order_id: createResponse.paypal_order_id,
                    method: this.methodCode,
                    form_key: FORM_KEY,
                }),
            });

            if (approveResponse.success && approveResponse.redirect_url) {
                window.location.href = approveResponse.redirect_url;
                return;
            }

            throw new Error(approveResponse.message || 'Payment failed');
        } catch (err) {
            console.error('Vault checkout error:', err);
            alert(err.message || 'Payment failed. Please try again.');
            this.submitting = false;
            checkout.setLoadWaiting(false);
        }
    }

    static getActiveInstance() {
        const currentMethod = payment?.currentMethod;
        if (!currentMethod) return null;

        const formDiv = document.getElementById(`payment_form_${currentMethod}`);
        return formDiv?._paypalVault || null;
    }
}

document.addEventListener('payment-method:switched', function(e) {
    const formDiv = e.target;
    if (!formDiv.dataset.vault || formDiv._paypalVault) return;
    formDiv._paypalVault = new MahoPaypalVaultCheckout(formDiv);
}, true);

// Intercept Review.save() to handle vault payments
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Review === 'undefined') return;
    const _origReviewSave = Review.prototype.save;
    Review.prototype.save = function() {
        const vault = MahoPaypalVaultCheckout.getActiveInstance();
        if (vault) {
            vault.submit();
            return;
        }
        return _origReviewSave.call(this);
    };
});
