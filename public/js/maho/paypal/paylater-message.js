/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MahoPaypalPayLaterMessage {
    constructor(el) {
        this.el = el;
        this.sdkUrl = el.dataset.sdkUrl;
        this.clientTokenUrl = el.dataset.clientTokenUrl;
        this.amount = el.dataset.amount || '0';
        this.placement = el.dataset.placement || 'product';
        this._mounted = false;
    }

    async loadSdkAndMount() {
        if (this._mounted) return;

        try {
            const sdk = await MahoPaypalSdk.init(this.sdkUrl, this.clientTokenUrl);
            this._renderMessage(sdk);
        } catch (err) {
            console.error('PayPal Pay Later message error:', err);
        }
    }

    _renderMessage(sdk) {
        if (this._mounted) return;
        this._mounted = true;

        if (typeof sdk.createPayPalMessages !== 'function') {
            return;
        }

        const messagesInstance = sdk.createPayPalMessages();
        const messageEl = document.createElement('paypal-message');
        messageEl.setAttribute('amount', this.amount);
        this.el.appendChild(messageEl);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paypal-paylater-message[data-client-token-url]').forEach(function(el) {
        const message = new MahoPaypalPayLaterMessage(el);
        el._paypalPayLater = message;
        message.loadSdkAndMount();
    });
});
