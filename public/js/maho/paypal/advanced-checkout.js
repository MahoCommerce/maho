/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalAdvancedCheckout {
    constructor(config) {
        this.createOrderUrl = config.createOrderUrl;
        this.approveOrderUrl = config.approveOrderUrl;
        this.methodCode = config.methodCode || 'paypal_advanced_checkout';
        this.cardFields = null;
        this._mounted = false;
    }

    mount() {
        if (this._mounted || typeof paypalCardFields === 'undefined') {
            return;
        }

        this.cardFields = paypalCardFields.CardFields({
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
