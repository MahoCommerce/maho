/**
 * Wishlist Module - Handles wishlist with guest localStorage + logged-in sync
 */
export default {
    // State
    wishlist: [],
    wishlistLoading: false,
    guestWishlist: JSON.parse(localStorage.getItem('guestWishlist') || '[]'),

    /**
     * Check if a product is in wishlist (guest or logged-in)
     */
    isInWishlist(productId) {
        if (this.token) {
            return this.wishlist.some(item => item.productId === productId);
        }
        return this.guestWishlist.includes(productId);
    },

    /**
     * Toggle product in wishlist
     */
    async toggleWishlist(productId) {
        if (this.isInWishlist(productId)) {
            await this.removeFromWishlist(productId);
        } else {
            await this.addToWishlist(productId);
        }
    },

    /**
     * Add product to wishlist
     */
    async addToWishlist(productId) {
        if (!this.token) {
            // Guest: save to localStorage
            if (!this.guestWishlist.includes(productId)) {
                this.guestWishlist.push(productId);
                localStorage.setItem('guestWishlist', JSON.stringify(this.guestWishlist));
                this.success = 'Added to wishlist';
            }
            return;
        }

        // Logged in: save to API
        this.wishlistLoading = true;
        try {
            const response = await this.api('/customers/me/wishlist', {
                method: 'POST',
                body: JSON.stringify({ productId, qty: 1 })
            });

            // Add to local state
            if (response && response.id) {
                this.wishlist.push(response);
            }
            this.success = 'Added to wishlist';
        } catch (e) {
            this.error = 'Failed to add to wishlist: ' + e.message;
        }
        this.wishlistLoading = false;
    },

    /**
     * Remove product from wishlist
     */
    async removeFromWishlist(productId) {
        if (!this.token) {
            // Guest: remove from localStorage
            this.guestWishlist = this.guestWishlist.filter(id => id !== productId);
            localStorage.setItem('guestWishlist', JSON.stringify(this.guestWishlist));
            this.success = 'Removed from wishlist';
            return;
        }

        // Logged in: remove via API
        const item = this.wishlist.find(w => w.productId === productId);
        if (!item) return;

        this.wishlistLoading = true;
        try {
            await fetch('/api/customers/me/wishlist/' + item.id, {
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + this.token
                }
            });

            // Remove from local state
            this.wishlist = this.wishlist.filter(w => w.id !== item.id);
            this.success = 'Removed from wishlist';
        } catch (e) {
            this.error = 'Failed to remove from wishlist: ' + e.message;
        }
        this.wishlistLoading = false;
    },

    /**
     * Load wishlist from API (logged-in users)
     */
    async loadWishlist() {
        if (!this.token) return;

        this.wishlistLoading = true;
        try {
            const response = await this.api('/customers/me/wishlist');
            this.wishlist = response.member || response['hydra:member'] || response || [];
        } catch (e) {
            console.error('Failed to load wishlist:', e);
            this.wishlist = [];
        }
        this.wishlistLoading = false;
    },

    /**
     * Sync guest wishlist with account after login
     */
    async syncGuestWishlist() {
        if (!this.token || this.guestWishlist.length === 0) return;

        try {
            const response = await this.api('/customers/me/wishlist/sync', {
                method: 'POST',
                body: JSON.stringify({ productIds: this.guestWishlist })
            });

            // Clear guest wishlist after sync
            this.guestWishlist = [];
            localStorage.removeItem('guestWishlist');

            // Reload wishlist to get all items
            await this.loadWishlist();

            if (response && response.length > 0) {
                this.success = `Synced ${response.length} item(s) to your wishlist`;
            }
        } catch (e) {
            console.error('Failed to sync guest wishlist:', e);
        }
    },

    /**
     * Move wishlist item to cart
     */
    async moveWishlistToCart(itemId) {
        if (!this.token) return;

        // Ensure we have a cart
        await this.ensureCart();

        this.wishlistLoading = true;
        try {
            await this.api('/customers/me/wishlist/' + itemId + '/move-to-cart', {
                method: 'POST',
                body: JSON.stringify({ qty: 1, cartId: this.cartId })
            });

            // Remove from wishlist state
            this.wishlist = this.wishlist.filter(w => w.id !== itemId);

            // Reload cart
            await this.loadCart();

            this.success = 'Moved to cart';
        } catch (e) {
            this.error = 'Failed to move to cart: ' + e.message;
        }
        this.wishlistLoading = false;
    },

    /**
     * Get wishlist count for header badge
     */
    get wishlistCount() {
        if (this.token) {
            return this.wishlist.length;
        }
        return this.guestWishlist.length;
    }
};
