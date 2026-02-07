/**
 * Catalog Module - Handles categories, products listing, search, filters
 */
export default {
    // State
    products: [],
    categories: [],
    topCategories: [],
    subcategories: [],
    currentCategory: null,
    currentTopCategory: null,
    searchQuery: '',
    sortBy: '',
    priceMin: 0,
    priceMax: 500,
    priceSliderMin: 0,
    priceSliderMax: 500,
    priceFilterActive: false,
    page: 1,
    pageSize: 12,
    totalPages: 1,

    async loadCategories() {
        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const data = await this.gql(this._gqlQueries.CATEGORIES_QUERY);
                this.categories = this._gqlNodes(data, 'categoriesCategories');
                this.topCategories = this.categories.slice(0, 5);
            } catch (e) {
                console.error('Failed to load categories (GQL):', e);
                this.categories = [];
                this.topCategories = [];
            }
            return;
        }

        // REST branch
        try {
            const data = await this.api('/categories');
            this.categories = data.member || data.items || data || [];
            this.topCategories = this.categories.slice(0, 5);
        } catch (e) {
            console.error('Failed to load categories:', e);
            this.categories = [];
            this.topCategories = [];
        }
    },

    async loadProducts() {
        this.loading = true;

        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const variables = {
                    pageSize: this.pageSize,
                    page: this.page
                };
                if (this.searchQuery) variables.search = this.searchQuery;
                if (this.currentCategory) variables.categoryId = this.currentCategory;
                if (this.priceFilterActive && this.priceMin > 0) variables.priceMin = this.priceMin;
                if (this.priceFilterActive && this.priceMax < this.priceSliderMax) variables.priceMax = this.priceMax;
                if (this.sortBy) {
                    const [field, dir] = this.sortBy.split('-');
                    variables.sortBy = field;
                    variables.sortDir = dir;
                }

                const data = await this.gql(this._gqlQueries.PRODUCTS_QUERY, variables);
                this.products = this._gqlNodes(data, 'productsProducts');
                const totalItems = data.productsProducts?.totalCount || this.products.length;
                this.totalPages = Math.ceil(totalItems / this.pageSize) || 1;
                this.updateUrl();
            } catch (e) {
                this.error = 'Failed to load products: ' + e.message;
                this.products = [];
            }
            this.loading = false;
            return;
        }

        // REST branch
        try {
            const params = new URLSearchParams();
            params.append('pageSize', this.pageSize);
            params.append('page', this.page);
            if (this.searchQuery) params.append('search', this.searchQuery);
            if (this.currentCategory) params.append('categoryId', this.currentCategory);
            if (this.priceFilterActive && this.priceMin > 0) params.append('priceMin', this.priceMin);
            if (this.priceFilterActive && this.priceMax < this.priceSliderMax) params.append('priceMax', this.priceMax);
            if (this.sortBy) {
                const [field, dir] = this.sortBy.split('-');
                params.append('sortBy', field);
                params.append('sortDir', dir);
            }

            const data = await this.api('/products?' + params.toString());
            this.products = data.member || data['hydra:member'] || data.items || data || [];
            const totalItems = data.totalItems || data['hydra:totalItems'] || data.totalCount || this.products.length;
            this.totalPages = Math.ceil(totalItems / this.pageSize) || 1;
            this.updateUrl();
        } catch (e) {
            this.error = 'Failed to load products: ' + e.message;
            this.products = [];
        }
        this.loading = false;
    },

    async loadCategory(catId, isSubcategory = false, urlKey = null) {
        this.currentCategory = catId;
        this._currentCategoryUrlKey = urlKey || this._currentCategoryUrlKey;
        this.page = 1;
        this.view = 'category';
        this.mobileMenuOpen = false;
        window.scrollTo({ top: 0, behavior: 'instant' });
        // Clear search when navigating to a category
        this.searchQuery = '';

        if (!isSubcategory) {
            this.currentTopCategory = catId;
            await this.loadSubcategories(catId);
        }

        this.loadProducts();
        this.updateUrl();
    },

    async loadSubcategories(parentId) {
        // GraphQL branch
        if (this.useGraphQL && this._gqlQueries) {
            try {
                const data = await this.gql(this._gqlQueries.CATEGORIES_QUERY, { parentId });
                this.subcategories = this._gqlNodes(data, 'categoriesCategories');
            } catch (e) {
                console.error('Failed to load subcategories (GQL):', e);
                this.subcategories = [];
            }
            return;
        }

        // REST branch
        try {
            const data = await this.api('/categories?parentId=' + parentId);
            this.subcategories = data.member || data.items || data || [];
        } catch (e) {
            console.error('Failed to load subcategories:', e);
            this.subcategories = [];
        }
    },

    searchProducts() {
        if (!this.searchQuery.trim()) return;
        // Clear category when searching
        this.currentCategory = null;
        this.currentTopCategory = null;
        this.page = 1;
        this.view = 'search';
        this.loadProducts();
        this.updateUrl();
    },

    // Apply price filter
    applyPriceFilter() {
        this.priceFilterActive = true;
        this.page = 1;
        this.loadProducts();
    },

    // Clear price filter
    clearPriceFilter() {
        this.priceFilterActive = false;
        this.priceMin = this.priceSliderMin;
        this.priceMax = this.priceSliderMax;
        this.page = 1;
        this.loadProducts();
    }
};
