/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalVaultCheckout {
    constructor(formDiv) {
        this.createOrderUrl = formDiv.dataset.createOrderUrl;
        this.approveOrderUrl = formDiv.dataset.approveOrderUrl;
        this.methodCode = formDiv.dataset.methodCode;
    }

    async submit() {
        const tokenSelect = document.getElementById('paypal_vault_token');
        if (!tokenSelect || !tokenSelect.value) {
            return false;
        }

        try {
            const createResponse = await mahoFetch(this.createOrderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    method: this.methodCode,
                    vault_token_id: tokenSelect.value,
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
                }),
            });

            if (approveResponse.success && approveResponse.redirect_url) {
                window.location.href = approveResponse.redirect_url;
                return true;
            }

            throw new Error(approveResponse.message || 'Payment failed');
        } catch (err) {
            console.error('Vault checkout error:', err);
            return false;
        }
    }
}

document.addEventListener('payment-method:switched', function(e) {
    const formDiv = e.target;
    if (!formDiv.dataset.vault || formDiv._paypalVault) return;

    const checkout = new MahoPaypalVaultCheckout(formDiv);
    formDiv._paypalVault = checkout;

    if (typeof payment !== 'undefined') {
        payment.addBeforeValidateFunc(formDiv.dataset.methodCode, function() {
            return checkout.submit();
        });
    }
}, true);
