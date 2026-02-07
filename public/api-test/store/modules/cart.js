/**
 * Cart Module - Handles shopping cart operations
 */
export default {
    // State
    cartId: localStorage.getItem('cartId'),
    cart: {},
    cartCount: 0,
    couponCode: '',

    async ensureCart() {
        if (!this.cartId) {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.CREATE_CART, { input: {} });
                const cart = data.createCartCart?.cart;
                this.cartId = cart?.maskedId || cart?.id;
                localStorage.setItem('cartId', this.cartId);
                return this.cartId;
            }

            // REST branch
            const data = await this.api('/guest-carts', { method: 'POST', body: '{}' });
            this.cartId = data.id || data.cartId;
            localStorage.setItem('cartId', this.cartId);
        }
        return this.cartId;
    },

    async loadCart() {
        if (!this.cartId) return;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const data = await this.gql(this._gqlQueries.CART_QUERY, { maskedId: this.cartId });
                this.cart = data.getCartByMaskedIdCart;
                this.cartCount = this.cart.items?.length || 0;
            } catch (e) {
                // Cart might be expired
                this.cartId = null;
                localStorage.removeItem('cartId');
                this.cart = {};
                this.cartCount = 0;
            }
            return;
        }

        // REST branch
        try {
            this.cart = await this.api('/guest-carts/' + this.cartId);
            this.cartCount = this.cart.items?.length || 0;
        } catch (e) {
            // Cart might be expired
            this.cartId = null;
            localStorage.removeItem('cartId');
            this.cart = {};
            this.cartCount = 0;
        }
    },

    async addToCart(sku, qty = 1, options = null, links = null) {
        this.loading = true;
        try {
            await this.ensureCart();

            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const input = { maskedId: this.cartId, sku, qty };
                if (options && Object.keys(options).length > 0) input.options = options;
                if (links && links.length > 0) input.links = links;
                const data = await this.gql(this._gqlQueries.ADD_TO_CART, { input });
                // Update cart directly from mutation response
                this.cart = data.addToCartCart?.cart || {};
                this.cartCount = this.cart.items?.length || 0;
            } else {
                // REST branch
                const payload = { sku, qty };
                // Add custom options if provided
                if (options && Object.keys(options).length > 0) {
                    payload.options = options;
                }
                await this.api('/guest-carts/' + this.cartId + '/items', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                await this.loadCart();
            }

            this.success = 'Added to cart!';
            setTimeout(() => this.success = null, 2000);
        } catch (e) {
            this.error = 'Failed to add to cart: ' + e.message;
        }
        this.loading = false;
    },

    async updateCartItem(itemId, qty) {
        if (qty < 1) {
            return this.removeCartItem(itemId);
        }
        this.loading = true;
        try {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.UPDATE_CART_ITEM, {
                    input: { maskedId: this.cartId, itemId: String(itemId), qty }
                });
                this.cart = data.updateCartItemQtyCart?.cart || {};
                this.cartCount = this.cart.items?.length || 0;
            } else {
                // REST branch
                await this.api('/guest-carts/' + this.cartId + '/items/' + itemId, {
                    method: 'PUT',
                    body: JSON.stringify({ qty })
                });
                await this.loadCart();
            }
        } catch (e) {
            this.error = 'Failed to update cart: ' + e.message;
        }
        this.loading = false;
    },

    async removeCartItem(itemId) {
        this.loading = true;
        try {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.REMOVE_CART_ITEM, {
                    input: { maskedId: this.cartId, itemId: String(itemId) }
                });
                this.cart = data.removeCartItemCart?.cart || {};
                this.cartCount = this.cart.items?.length || 0;
            } else {
                // REST branch
                await this.api('/guest-carts/' + this.cartId + '/items/' + itemId, {
                    method: 'DELETE'
                });
                await this.loadCart();
            }
        } catch (e) {
            this.error = 'Failed to remove item: ' + e.message;
        }
        this.loading = false;
    },

    async applyCoupon() {
        if (!this.couponCode) return;
        this.loading = true;
        try {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.APPLY_COUPON, {
                    input: { maskedId: this.cartId, couponCode: this.couponCode }
                });
                this.cart = { ...this.cart, ...data.applyCouponToCartCart?.cart };
                this.success = 'Coupon applied!';
                this.couponCode = '';
                await this.loadCart();
            } else {
                // REST branch
                await this.api('/guest-carts/' + this.cartId + '/coupon', {
                    method: 'PUT',
                    body: JSON.stringify({ couponCode: this.couponCode })
                });
                await this.loadCart();
                this.success = 'Coupon applied!';
                this.couponCode = '';
            }
        } catch (e) {
            this.error = 'Invalid coupon code';
        }
        this.loading = false;
    }
};
