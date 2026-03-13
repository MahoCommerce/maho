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
        this.amount = parseFloat(el.dataset.amount) || 0;
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

        if (!sdk.Messages) {
            console.warn('PayPal Messages component not available');
            return;
        }

        sdk.Messages({
            amount: this.amount,
            placement: this.placement,
            style: {
                layout: 'text',
                logo: {
                    type: 'primary',
                    position: 'left',
                },
            },
        }).render(this.el);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paypal-paylater-message[data-client-token-url]').forEach(function(el) {
        const message = new MahoPaypalPayLaterMessage(el);
        el._paypalPayLater = message;
        message.loadSdkAndMount();
    });
});
