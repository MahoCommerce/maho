/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalVaultCheckout {
    constructor(config) {
        this.createOrderUrl = config.createOrderUrl;
        this.approveOrderUrl = config.approveOrderUrl;
        this.methodCode = config.methodCode || 'paypal_vault';
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
