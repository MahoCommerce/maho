/**
 * Product Module - Handles product viewing, configurable options, variants,
 * grouped products, and bundle products
 */
export default {
    // State
    currentProduct: {},
    qty: 1,
    selectedOptions: {},  // For configurable products: { attributeCode: valueId }
    selectedCustomOptions: {},  // For custom options: { optionId: valueId }
    groupedQtys: {},  // For grouped products: { childProductId: qty }
    selectedBundleOptions: {},  // For bundle products: { optionId: selectionId or [selectionIds] }
    bundleOptionQtys: {},  // For bundle products: { optionId: qty }

    // View a product by ID (numeric) with optional urlKey for nice URLs
    async viewProduct(id, urlKey = null) {
        this.loading = true;
        window.scrollTo({ top: 0, behavior: 'instant' });
        // If called with a slug (from URL resolver), resolve to numeric ID first
        const isNumeric = /^\d+$/.test(String(id));
        let productId = id;

        try {
            // Load wishlist and reviews modules for product page
            await Promise.all([
                this.loadModule('wishlist'),
                this.loadModule('reviews')
            ]);

            if (!isNumeric) {
                const resolved = await this.api('/url-resolver?path=' + encodeURIComponent(id));
                const result = (resolved.member || resolved['hydra:member'] || [])[0];
                if (result?.type === 'product' && result.id) {
                    productId = result.id;
                } else {
                    throw new Error('Product not found');
                }
            }

            // GraphQL branch
            if (this.useGraphQL && this._gqlQueries) {
                const data = await this.gql(this._gqlQueries.PRODUCT_QUERY, { id: '/api/products/' + productId });
                const product = data.productProduct;
                if (product) product.id = this._parseId(product.id);
                this.currentProduct = product;
            } else {
                // REST branch
                this.currentProduct = await this.api('/products/' + productId);
            }

            this.qty = 1;
            this.selectedOptions = {};
            this.selectedCustomOptions = {};
            this.selectedDownloadableLinks = [];
            this.groupedQtys = {};
            this.selectedBundleOptions = {};
            this.bundleOptionQtys = {};

            // Auto-select all downloadable links when not purchased separately
            if (this.currentProduct?.type === 'downloadable' && !this.currentProduct.linksPurchasedSeparately) {
                this.selectedDownloadableLinks = (this.currentProduct.downloadableLinks || []).map(l => l.id);
            }

            // Init grouped product quantities from defaults
            if (this.currentProduct?.type === 'grouped' && this.currentProduct.groupedProducts?.length) {
                for (const child of this.currentProduct.groupedProducts) {
                    this.groupedQtys[child.id] = child.defaultQty || 0;
                }
            }

            // Init bundle option selections from defaults
            if (this.currentProduct?.type === 'bundle' && this.currentProduct.bundleOptions?.length) {
                for (const option of this.currentProduct.bundleOptions) {
                    const isMulti = option.type === 'checkbox' || option.type === 'multi';
                    const defaults = option.selections?.filter(s => s.isDefault) || [];

                    if (isMulti) {
                        this.selectedBundleOptions[option.id] = defaults.map(s => s.id);
                    } else if (defaults.length > 0) {
                        this.selectedBundleOptions[option.id] = defaults[0].id;
                    } else if (option.selections?.length === 1) {
                        // Auto-select single selection
                        this.selectedBundleOptions[option.id] = option.selections[0].id;
                    }

                    // Init quantities from default selections
                    if (defaults.length > 0) {
                        this.bundleOptionQtys[option.id] = defaults[0].defaultQty || 1;
                    } else if (option.selections?.length) {
                        this.bundleOptionQtys[option.id] = option.selections[0].defaultQty || 1;
                    }
                }
            }

            // Auto-select options with only one value
            this.autoSelectSingleOptions();

            this.view = 'product';
            this.updateUrl();

            // Load product reviews
            const reviewProductId = this.currentProduct?.id;
            if (this.loadProductReviews && reviewProductId) {
                this.loadProductReviews(reviewProductId);
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

    // Check if all required options are selected (configurable + custom + grouped + bundle)
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

        // Grouped: at least one child must have qty > 0
        if (this.currentProduct?.type === 'grouped') {
            const hasAnyQty = Object.values(this.groupedQtys).some(q => parseInt(q) > 0);
            if (!hasAnyQty) return false;
        }

        // Bundle: all required options must be selected
        if (this.currentProduct?.type === 'bundle' && this.currentProduct.bundleOptions?.length) {
            for (const option of this.currentProduct.bundleOptions) {
                if (!option.required) continue;
                const selected = this.selectedBundleOptions[option.id];
                const isMulti = option.type === 'checkbox' || option.type === 'multi';
                if (isMulti) {
                    if (!selected || !Array.isArray(selected) || selected.length === 0) return false;
                } else {
                    if (!selected) return false;
                }
            }
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

    // Get SKU for add to cart (variant SKU for configurable, parent SKU for grouped/bundle)
    getCartSku() {
        if (this.currentProduct?.type === 'configurable') {
            const variant = this.getSelectedVariant();
            return variant?.sku || null;
        }
        // Grouped and bundle use parent SKU
        return this.currentProduct?.sku;
    },

    // Get grouped product super_group map: { childId: qty } (filtered to qty > 0)
    getGroupedSuperGroup() {
        const superGroup = {};
        for (const [childId, qty] of Object.entries(this.groupedQtys)) {
            const parsedQty = parseInt(qty);
            if (parsedQty > 0) {
                superGroup[childId] = parsedQty;
            }
        }
        return superGroup;
    },

    // Get bundle cart options: { bundle_option: {...}, bundle_option_qty: {...} }
    getBundleCartOptions() {
        const bundleOption = {};
        const bundleOptionQty = {};

        for (const option of (this.currentProduct?.bundleOptions || [])) {
            const selected = this.selectedBundleOptions[option.id];
            if (selected !== undefined && selected !== null && selected !== '') {
                bundleOption[option.id] = selected;
                bundleOptionQty[option.id] = this.bundleOptionQtys[option.id] || 1;
            }
        }

        return { bundle_option: bundleOption, bundle_option_qty: bundleOptionQty };
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
