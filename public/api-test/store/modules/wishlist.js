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

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            this.wishlistLoading = true;
            try {
                const data = await this.gql(this._gqlQueries.ADD_TO_WISHLIST, { input: { productId, qty: 1 } });
                const item = data.addToWishlistWishlistItem?.wishlistItem;
                if (item?.id) {
                    this.wishlist.push(item);
                }
                this.success = 'Added to wishlist';
            } catch (e) {
                this.error = 'Failed to add to wishlist: ' + e.message;
            }
            this.wishlistLoading = false;
            return;
        }

        // REST branch
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

        // Logged in: find item by productId
        const item = this.wishlist.find(w => w.productId === productId);
        if (!item) return;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            this.wishlistLoading = true;
            try {
                await this.gql(this._gqlQueries.REMOVE_FROM_WISHLIST, { input: { itemId: item.id } });
                this.wishlist = this.wishlist.filter(w => w.id !== item.id);
                this.success = 'Removed from wishlist';
            } catch (e) {
                this.error = 'Failed to remove from wishlist: ' + e.message;
            }
            this.wishlistLoading = false;
            return;
        }

        // REST branch
        this.wishlistLoading = true;
        try {
            await this.api('/customers/me/wishlist/' + item.id, {
                method: 'DELETE'
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

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            this.wishlistLoading = true;
            try {
                const data = await this.gql(this._gqlQueries.MY_WISHLIST_QUERY);
                this.wishlist = this._gqlNodes(data, 'myWishlistWishlistItems');
            } catch (e) {
                console.error('Failed to load wishlist (GQL):', e);
                this.wishlist = [];
            }
            this.wishlistLoading = false;
            return;
        }

        // REST branch
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

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                await this.gql(this._gqlQueries.SYNC_WISHLIST, { input: { productIds: this.guestWishlist } });
                this.guestWishlist = [];
                localStorage.removeItem('guestWishlist');
                await this.loadWishlist();
            } catch (e) {
                console.error('Failed to sync guest wishlist (GQL):', e);
            }
            return;
        }

        // REST branch
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

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            this.wishlistLoading = true;
            try {
                await this.gql(this._gqlQueries.MOVE_WISHLIST_TO_CART, { input: { itemId, qty: 1 } });
                this.wishlist = this.wishlist.filter(w => w.id !== itemId);
                await this.loadCart();
                this.success = 'Moved to cart';
            } catch (e) {
                this.error = 'Failed to move to cart: ' + e.message;
            }
            this.wishlistLoading = false;
            return;
        }

        // REST branch
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
