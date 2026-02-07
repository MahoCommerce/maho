/**
 * Reviews Module - Handles product reviews
 */
export default {
    // State
    productReviews: [],
    reviewsLoading: false,
    reviewsPage: 1,
    reviewsTotalPages: 1,
    showReviewForm: false,
    reviewForm: {
        title: '',
        detail: '',
        nickname: '',
        rating: 5
    },
    myReviews: [],

    /**
     * Load reviews for a product
     */
    async loadProductReviews(productId, page = 1) {
        this.reviewsLoading = true;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const data = await this.gql(this._gqlQueries.PRODUCT_REVIEWS_QUERY, {
                    productId: parseInt(productId),
                    page,
                    pageSize: 10
                });
                this.productReviews = this._gqlNodes(data, 'productReviewsReviews');
                this.reviewsPage = page;
                const totalReviews = data.productReviewsReviews?.totalCount || this.productReviews.length;
                this.reviewsTotalPages = Math.ceil(totalReviews / 10) || 1;
            } catch (e) {
                console.error('Failed to load reviews (GQL):', e);
                this.productReviews = [];
            }
            this.reviewsLoading = false;
            return;
        }

        // REST branch
        try {
            const response = await this.api(`/products/${productId}/reviews?page=${page}&pageSize=10`);
            this.productReviews = response.member || response['hydra:member'] || response || [];
            this.reviewsPage = page;
            // Estimate total pages (API should provide this)
            const total = response.totalItems || response['hydra:totalItems'] || this.productReviews.length;
            this.reviewsTotalPages = Math.ceil(total / 10) || 1;
        } catch (e) {
            console.error('Failed to load reviews:', e);
            this.productReviews = [];
        }
        this.reviewsLoading = false;
    },

    /**
     * Submit a product review
     */
    async submitReview(productId) {
        if (!this.token) {
            this.error = 'Please sign in to write a review';
            return;
        }

        // Validate
        if (!this.reviewForm.title.trim()) {
            this.error = 'Please enter a review title';
            return;
        }
        if (!this.reviewForm.detail.trim()) {
            this.error = 'Please enter your review';
            return;
        }
        if (!this.reviewForm.nickname.trim()) {
            this.error = 'Please enter a nickname';
            return;
        }

        this.reviewsLoading = true;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                await this.gql(this._gqlQueries.SUBMIT_REVIEW, {
                    input: {
                        productId: parseInt(productId),
                        title: this.reviewForm.title,
                        detail: this.reviewForm.detail,
                        nickname: this.reviewForm.nickname,
                        rating: this.reviewForm.rating
                    }
                });
                this.success = 'Thank you! Your review has been submitted for approval.';
                this.showReviewForm = false;
                this.resetReviewForm();
            } catch (e) {
                this.error = 'Failed to submit review: ' + e.message;
            }
            this.reviewsLoading = false;
            return;
        }

        // REST branch
        try {
            await this.api(`/products/${productId}/reviews`, {
                method: 'POST',
                body: JSON.stringify({
                    title: this.reviewForm.title,
                    detail: this.reviewForm.detail,
                    nickname: this.reviewForm.nickname,
                    rating: this.reviewForm.rating
                })
            });

            this.success = 'Thank you! Your review has been submitted for approval.';
            this.showReviewForm = false;
            this.resetReviewForm();
        } catch (e) {
            this.error = 'Failed to submit review: ' + e.message;
        }
        this.reviewsLoading = false;
    },

    /**
     * Reset review form
     */
    resetReviewForm() {
        this.reviewForm = {
            title: '',
            detail: '',
            nickname: this.customer?.firstName || '',
            rating: 5
        };
    },

    /**
     * Load current customer's reviews
     */
    async loadMyReviews() {
        if (!this.token) return;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const data = await this.gql(this._gqlQueries.MY_REVIEWS_QUERY);
                this.myReviews = this._gqlNodes(data, 'myReviewsReviews');
            } catch (e) {
                console.error('Failed to load my reviews (GQL):', e);
                this.myReviews = [];
            }
            return;
        }

        // REST branch
        try {
            const response = await this.api('/customers/me/reviews');
            this.myReviews = response.member || response['hydra:member'] || response || [];
        } catch (e) {
            console.error('Failed to load my reviews:', e);
            this.myReviews = [];
        }
    },

    /**
     * Get average rating for display
     */
    getAverageRating(reviews) {
        if (!reviews || reviews.length === 0) return 0;
        const sum = reviews.reduce((acc, r) => acc + r.rating, 0);
        return (sum / reviews.length).toFixed(1);
    },

    /**
     * Generate star display HTML
     */
    getStarDisplay(rating, max = 5) {
        const fullStars = Math.floor(rating);
        const hasHalf = rating % 1 >= 0.5;
        let stars = '';

        for (let i = 0; i < max; i++) {
            if (i < fullStars) {
                stars += '\u2605';
            } else if (i === fullStars && hasHalf) {
                stars += '\u2606'; // Use empty star for half (or could use different char)
            } else {
                stars += '\u2606';
            }
        }
        return stars;
    }
};
