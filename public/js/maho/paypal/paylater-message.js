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
        this.amount = el.dataset.amount;
        this.currency = el.dataset.currency;
        this._mounted = false;
    }

    async mount() {
        if (this._mounted) return;

        try {
            const sdk = await MahoPaypalSdk.init(this.sdkUrl, this.clientTokenUrl);
            this._render(sdk);
        } catch (err) {
            console.error('PayPal Pay Later message error:', err);
        }
    }

    _render(sdk) {
        if (this._mounted) return;
        this._mounted = true;

        if (typeof sdk.createPayPalMessages !== 'function') {
            return;
        }

        const messagesInstance = sdk.createPayPalMessages({
            currencyCode: this.currency,
            placement: this.el.dataset.placement || 'product',
        });
        const messageEl = document.createElement('paypal-message');
        this.el.appendChild(messageEl);

        messagesInstance.fetchContent({
            amount: this.amount,
            currencyCode: this.currency,
            onContentReady: (content) => messageEl.setContent(content),
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paypal-paylater-message[data-client-token-url]').forEach(function(el) {
        const message = new MahoPaypalPayLaterMessage(el);
        el._paypalPayLater = message;
        message.mount();
    });
});
