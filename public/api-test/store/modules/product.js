/**
 * Product Module - Handles product viewing, configurable options, variants
 */
export default {
    // State
    currentProduct: {},
    qty: 1,
    selectedOptions: {},  // For configurable products: { attributeCode: valueId }
    selectedCustomOptions: {},  // For custom options: { optionId: valueId }

    // View a product by ID
    async viewProduct(id) {
        this.loading = true;
        try {
            // Load wishlist and reviews modules for product page
            await Promise.all([
                this.loadModule('wishlist'),
                this.loadModule('reviews')
            ]);

            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.PRODUCT_QUERY, { id: '/api/products/' + id });
                const product = data.productProduct;
                if (product) product.id = this._parseId(product.id);
                this.currentProduct = product;
            } else {
                // REST branch
                this.currentProduct = await this.api('/products/' + id);
            }

            this.qty = 1;
            this.selectedOptions = {};
            this.selectedCustomOptions = {};
            this.selectedDownloadableLinks = [];

            // Auto-select all downloadable links when not purchased separately
            if (this.currentProduct?.type === 'downloadable' && !this.currentProduct.linksPurchasedSeparately) {
                this.selectedDownloadableLinks = (this.currentProduct.downloadableLinks || []).map(l => l.id);
            }

            // Auto-select options with only one value
            this.autoSelectSingleOptions();

            this.view = 'product';
            this.updateUrl();

            // Load product reviews
            if (this.loadProductReviews) {
                this.loadProductReviews(id);
            }
        } catch (e) {
            this.error = 'Failed to load product: ' + e.message;
        }
        this.loading = false;
    },

    // Get the selected variant based on current option selections
    getSelectedVariant() {
        if (!this.currentProduct?.variants?.length) return null;
        const optionCount = Object.keys(this.selectedOptions).length;
        const requiredCount = this.currentProduct.configurableOptions?.length || 0;
        if (optionCount !== requiredCount) return null;

        return this.currentProduct.variants.find(variant => {
            return Object.entries(this.selectedOptions).every(([code, valueId]) => {
                return variant.attributes[code] === parseInt(valueId);
            });
        });
    },

    // Check if all required options are selected (configurable + custom)
    allOptionsSelected() {
        // Check configurable options
        if (this.currentProduct?.configurableOptions?.length) {
            const allConfigSelected = this.currentProduct.configurableOptions.every(opt =>
                this.selectedOptions[opt.code] !== undefined
            );
            if (!allConfigSelected) return false;
        }

        // Check required custom options
        if (this.currentProduct?.customOptions?.length) {
            const allCustomSelected = this.currentProduct.customOptions
                .filter(opt => opt.required)
                .every(opt => this.selectedCustomOptions[opt.id] !== undefined);
            if (!allCustomSelected) return false;
        }

        return true;
    },

    // Get custom options for cart (formatted as { optionId: valueId })
    getCartCustomOptions() {
        const options = {};
        for (const [optionId, value] of Object.entries(this.selectedCustomOptions)) {
            options[optionId] = String(value);
        }
        return options;
    },

    // Get SKU for add to cart (variant SKU for configurable, product SKU for simple)
    getCartSku() {
        if (this.currentProduct?.type === 'configurable') {
            const variant = this.getSelectedVariant();
            return variant?.sku || null;
        }
        return this.currentProduct?.sku;
    },

    // Check if the selected variant (or any variant if none selected) is in stock
    isSelectedVariantInStock() {
        if (this.currentProduct?.type !== 'configurable') {
            return this.currentProduct?.stockStatus !== 'out_of_stock';
        }

        // If options are selected, check the specific variant
        if (this.allOptionsSelected()) {
            const variant = this.getSelectedVariant();
            return variant?.inStock ?? false;
        }

        // If no options selected yet, check if ANY variant is in stock
        return this.currentProduct?.variants?.some(v => v.inStock) ?? false;
    },

    // Check if a specific option value has any in-stock variants
    // Used to grey out unavailable options
    isOptionValueAvailable(optionCode, valueId) {
        if (!this.currentProduct?.variants?.length) return true;

        // Find variants that have this option value
        const matchingVariants = this.currentProduct.variants.filter(variant =>
            variant.attributes[optionCode] === parseInt(valueId)
        );

        // Check if any of the matching variants are in stock
        return matchingVariants.some(v => v.inStock);
    },

    // Get stock quantity for a specific option value (sum of all matching variants)
    getOptionValueStock(optionCode, valueId) {
        if (!this.currentProduct?.variants?.length) return 0;

        return this.currentProduct.variants
            .filter(variant => variant.attributes[optionCode] === parseInt(valueId))
            .reduce((sum, v) => sum + (v.stockQty || 0), 0);
    },

    // Auto-select options that only have one available value
    autoSelectSingleOptions() {
        // Auto-select configurable options with single value
        if (this.currentProduct?.configurableOptions?.length) {
            for (const option of this.currentProduct.configurableOptions) {
                // Filter to in-stock values only
                const availableValues = option.values.filter(v =>
                    this.isOptionValueAvailable(option.code, v.id)
                );

                if (availableValues.length === 1) {
                    this.selectedOptions[option.code] = availableValues[0].id;
                }
            }
        }

        // Auto-select custom options with single value (for dropdowns/radios)
        if (this.currentProduct?.customOptions?.length) {
            for (const option of this.currentProduct.customOptions) {
                if (option.values?.length === 1) {
                    this.selectedCustomOptions[option.id] = option.values[0].id;
                }
            }
        }
    }
};
