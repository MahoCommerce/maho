/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Extend AdminOrder with gift card methods
 * Uses deferred initialization to handle script load order
 */
(function() {
    function extendAdminOrder() {
        if (typeof AdminOrder === 'undefined') {
            return false;
        }

        AdminOrder.prototype.applyGiftcard = function(code) {
            if (!code || !code.trim()) {
                alert('Please enter a gift card code.');
                return;
            }
            this.loadArea(['items', 'shipping_method', 'totals', 'billing_method'], true, {
                'order[giftcard][code]': code.trim(),
                'order[giftcard][action]': 'apply',
                reset_shipping: true,
            });
        };

        AdminOrder.prototype.removeGiftcard = function(code) {
            this.loadArea(['items', 'shipping_method', 'totals', 'billing_method'], true, {
                'order[giftcard][code]': code,
                'order[giftcard][action]': 'remove',
                reset_shipping: true,
            });
        };

        return true;
    }

    // Try immediately
    if (!extendAdminOrder()) {
        // If AdminOrder not yet defined, wait for DOM ready
        document.addEventListener('DOMContentLoaded', extendAdminOrder);
    }
})();
