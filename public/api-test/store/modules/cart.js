/**
 * Cart Module - Handles shopping cart operations
 */
export default {
    // State
    cartId: localStorage.getItem('cartId'),
    cart: {},
    cartCount: 0,
    couponCode: '',
    giftcardCode: '',

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
            this.cartId = data.maskedId || data.id;
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

    async addToCart(sku, qty = 1, options = null, links = null, superGroup = null, bundleOption = null, bundleOptionQty = null) {
        this.loading = true;
        try {
            await this.ensureCart();

            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const input = { maskedId: this.cartId, sku, qty };
                if (options && Object.keys(options).length > 0) input.options = options;
                if (links && links.length > 0) input.links = links;
                if (superGroup && Object.keys(superGroup).length > 0) input.superGroup = superGroup;
                if (bundleOption && Object.keys(bundleOption).length > 0) input.bundleOption = bundleOption;
                if (bundleOptionQty && Object.keys(bundleOptionQty).length > 0) input.bundleOptionQty = bundleOptionQty;
                const data = await this.gql(this._gqlQueries.ADD_TO_CART, { input });
                // Update cart directly from mutation response
                this.cart = data.addToCartCart?.cart || {};
                this.cartCount = this.cart.items?.length || 0;
            } else {
                // REST branch
                const payload = { sku, qty };
                if (options && Object.keys(options).length > 0) payload.options = options;
                if (superGroup && Object.keys(superGroup).length > 0) payload.super_group = superGroup;
                if (bundleOption && Object.keys(bundleOption).length > 0) payload.bundle_option = bundleOption;
                if (bundleOptionQty && Object.keys(bundleOptionQty).length > 0) payload.bundle_option_qty = bundleOptionQty;
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
    },

    async applyGiftcard() {
        if (!this.giftcardCode) return;
        this.loading = true;
        this.error = null;
        try {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.APPLY_GIFTCARD, {
                    input: { maskedId: this.cartId, giftcardCode: this.giftcardCode }
                });
                this.cart = { ...this.cart, ...data.applyGiftcardToCartCart?.cart };
                this.cartCount = this.cart.items?.length || 0;
                this.success = 'Gift card applied!';
                this.giftcardCode = '';
                await this.loadCart();
            } else {
                // REST branch
                await this.api('/guest-carts/' + this.cartId + '/giftcard', {
                    method: 'POST',
                    body: JSON.stringify({ giftcardCode: this.giftcardCode })
                });
                await this.loadCart();
                this.success = 'Gift card applied!';
                this.giftcardCode = '';
            }
        } catch (e) {
            this.error = e.message || 'Invalid gift card code';
        }
        this.loading = false;
    },

    async removeGiftcard(code) {
        this.loading = true;
        this.error = null;
        try {
            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.REMOVE_GIFTCARD, {
                    input: { maskedId: this.cartId, giftcardCode: code }
                });
                this.cart = { ...this.cart, ...data.removeGiftcardFromCartCart?.cart };
                this.cartCount = this.cart.items?.length || 0;
                this.success = 'Gift card removed';
                await this.loadCart();
            } else {
                // REST branch
                await this.api('/guest-carts/' + this.cartId + '/giftcard', {
                    method: 'DELETE',
                    body: JSON.stringify({ giftcardCode: code })
                });
                await this.loadCart();
                this.success = 'Gift card removed';
            }
        } catch (e) {
            this.error = e.message || 'Failed to remove gift card';
        }
        this.loading = false;
    }
};
