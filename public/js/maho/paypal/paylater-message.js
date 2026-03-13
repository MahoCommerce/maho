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
        this.sdkNamespace = el.dataset.sdkNamespace;
        this.amount = parseFloat(el.dataset.amount) || 0;
        this.placement = el.dataset.placement || 'product';
        this._mounted = false;
    }

    loadSdkAndMount() {
        if (this._mounted) return;

        if (window[this.sdkNamespace]) {
            this._renderMessage();
            return;
        }

        const script = document.createElement('script');
        script.src = this.sdkUrl;
        script.dataset.namespace = this.sdkNamespace;
        script.onload = () => this._renderMessage();
        document.head.appendChild(script);
    }

    _renderMessage() {
        if (this._mounted) return;
        this._mounted = true;

        const sdk = window[this.sdkNamespace];
        if (!sdk?.Messages) return;

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
    document.querySelectorAll('.paypal-paylater-message[data-sdk-url]').forEach(function(el) {
        const message = new MahoPaypalPayLaterMessage(el);
        el._paypalPayLater = message;
        message.loadSdkAndMount();
    });
});
