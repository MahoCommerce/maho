/**
 * Checkout Module - Handles checkout flow, shipping, and order placement
 */
export default {
    // State
    checkout: {
        email: '',
        shipping: {
            firstName: '',
            lastName: '',
            street: '',
            city: '',
            postcode: '',
            region: '',
            regionId: null,
            countryId: 'AU',
            telephone: ''
        },
        shippingMethod: '',
        paymentMethod: ''
    },
    shippingMethods: [],
    shippingFetchTimeout: null,
    lastShippingAddressKey: '',
    countries: [],
    orderSummaryOpen: false,
    paymentMethods: [],
    paymentMethodsLoaded: false,
    lastOrderId: '',

    /**
     * Check if address has enough info to fetch shipping methods
     */
    isAddressCompleteForShipping() {
        const addr = this.checkout.shipping;
        return !!(addr.countryId && addr.city && addr.postcode && addr.street);
    },

    /**
     * Generate a key from address fields to detect changes
     */
    getShippingAddressKey() {
        const addr = this.checkout.shipping;
        return `${addr.countryId}|${addr.city}|${addr.postcode}|${addr.regionId || addr.region}|${addr.street}`;
    },

    /**
     * Called when shipping address fields change - debounced auto-fetch
     */
    onShippingAddressChange() {
        // Clear any pending fetch
        if (this.shippingFetchTimeout) {
            clearTimeout(this.shippingFetchTimeout);
        }

        // Check if address is complete
        if (!this.isAddressCompleteForShipping()) {
            return;
        }

        // Check if address actually changed
        const newKey = this.getShippingAddressKey();
        if (newKey === this.lastShippingAddressKey) {
            return;
        }

        // Debounce - wait 500ms after last change before fetching
        this.shippingFetchTimeout = setTimeout(async () => {
            this.lastShippingAddressKey = newKey;
            await this.getShippingMethods();
        }, 500);
    },

    async loadCountries() {
        if (this.countries.length > 0) return;
        try {
            const data = await this.api('/countries');
            this.countries = data.member || data || [];
        } catch (e) {
            console.error('Failed to load countries:', e);
            this.countries = [];
        }
    },

    /**
     * Prefill checkout with customer's saved address if logged in
     */
    async prefillCheckoutFromAccount() {
        // Only if logged in
        if (!this.token || !this.customer) return;

        // Set email from customer
        if (this.customer.email && !this.checkout.email) {
            this.checkout.email = this.customer.email;
        }

        // Load addresses if not already loaded
        if (!this.addresses || this.addresses.length === 0) {
            await this.loadModule('auth');
            await this.loadAccountData();
        }

        // Find best address: default shipping > default billing > first address
        if (this.addresses && this.addresses.length > 0) {
            let address = this.addresses.find(a => a.isDefaultShipping);
            if (!address) address = this.addresses.find(a => a.isDefaultBilling);
            if (!address) address = this.addresses[0];

            if (address) {
                this.checkout.shipping = {
                    firstName: address.firstName || '',
                    lastName: address.lastName || '',
                    street: Array.isArray(address.street) ? address.street.join(', ') : (address.street || ''),
                    city: address.city || '',
                    postcode: address.postcode || '',
                    region: address.region || '',
                    regionId: address.regionId || null,
                    countryId: address.countryId || 'AU',
                    telephone: address.telephone || ''
                };

                // Auto-fetch shipping methods after prefilling
                this.onShippingAddressChange();
            }
        }
    },

    async getShippingMethods() {
        this.loading = true;
        try {
            const data = await this.api('/guest-carts/' + this.cartId + '/shipping-methods', {
                method: 'POST',
                body: JSON.stringify({ address: this.checkout.shipping })
            });
            this.shippingMethods = data || [];
            if (this.shippingMethods.length > 0 && !this.checkout.shippingMethod) {
                this.checkout.shippingMethod = this.shippingMethods[0].code;
            }
        } catch (e) {
            this.error = 'Failed to get shipping methods: ' + e.message;
            // Mock data for testing
            this.shippingMethods = [
                { code: 'flatrate_flatrate', title: 'Flat Rate', description: '5-7 days', price: 10 },
                { code: 'freeshipping_freeshipping', title: 'Free Shipping', description: '7-10 days', price: 0 }
            ];
        }
        this.loading = false;
    },

    /**
     * Load available payment methods for the current cart
     */
    async loadPaymentMethods() {
        if (!this.cartId) return;

        try {
            const data = await this.api('/guest-carts/' + this.cartId + '/payment-methods');
            this.paymentMethods = data || [];
            this.paymentMethodsLoaded = true;

            // Auto-select first method if none selected
            if (this.paymentMethods.length > 0 && !this.checkout.paymentMethod) {
                this.checkout.paymentMethod = this.paymentMethods[0].code;
            }
        } catch (e) {
            console.error('Failed to load payment methods:', e);
            // Fallback to common offline methods
            this.paymentMethods = [
                { code: 'checkmo', title: 'Check / Money Order', isOffline: true },
                { code: 'cashondelivery', title: 'Cash on Delivery', isOffline: true }
            ];
            this.paymentMethodsLoaded = true;
        }
    },

    getSelectedShippingPrice() {
        const method = this.shippingMethods.find(m => m.code === this.checkout.shippingMethod);
        return method ? method.price : 0;
    },

    canPlaceOrder() {
        return this.checkout.email &&
               this.checkout.shipping.firstName &&
               this.checkout.shipping.lastName &&
               this.checkout.shipping.street &&
               this.checkout.shipping.city &&
               this.checkout.shipping.postcode &&
               this.checkout.shippingMethod &&
               this.checkout.paymentMethod;
    },

    async placeOrder() {
        if (!this.canPlaceOrder()) {
            this.error = 'Please fill in all required fields';
            return;
        }
        this.loading = true;
        try {
            const data = await this.api('/guest-carts/' + this.cartId + '/place-order', {
                method: 'POST',
                body: JSON.stringify({
                    email: this.checkout.email,
                    shippingAddress: this.checkout.shipping,
                    billingAddress: this.checkout.shipping,
                    shippingMethod: this.checkout.shippingMethod,
                    paymentMethod: this.checkout.paymentMethod
                })
            });
            this.lastOrderId = data.orderId || data.incrementId || data.id;

            // Clear cart
            this.cartId = null;
            localStorage.removeItem('cartId');
            this.cart = {};
            this.cartCount = 0;

            this.view = 'success';
        } catch (e) {
            this.error = 'Failed to place order: ' + e.message;
        }
        this.loading = false;
    }
};
