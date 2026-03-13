/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalStandardCheckout {
    constructor(config) {
        this.createOrderUrl = config.createOrderUrl;
        this.approveOrderUrl = config.approveOrderUrl;
        this.containerId = config.containerId || 'paypal-button-container';
        this.methodCode = config.methodCode || 'paypal_standard_checkout';
        this.onError = config.onError || null;
    }

    mount() {
        if (typeof paypal === 'undefined') {
            console.error('PayPal JS SDK not loaded');
            return;
        }

        paypal.Buttons({
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
        });

        if (response.success && response.redirect_url) {
            window.location.href = response.redirect_url;
        } else {
            this.handleError(new Error(response.message || 'Payment approval failed'));
        }
    }

    handleError(err) {
        console.error('PayPal error:', err);
        if (this.onError) {
            this.onError(err);
        }
    }
}
