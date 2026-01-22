/**
 * Store - Main Alpine.js store with lazy-loaded modules
 */
function store() {
    const BASE_PATH = '/api-test/store';

    // Track loaded modules
    const loadedModules = new Set();

    return {
        // Core State
        view: 'home',
        loading: false,
        error: null,
        success: null,
        currentPath: '',

        // CMS & Blog
        cmsPage: null,
        footerPages: [],
        blogPosts: [],
        currentBlogPost: null,

        // Store switching
        stores: [],
        currentStoreCode: localStorage.getItem('storeCode') || 'default',

        // Module state placeholders (will be populated by modules)
        // Product
        currentProduct: {},
        qty: 1,
        selectedOptions: {},
        selectedCustomOptions: {},

        // Catalog
        products: [],
        categories: [],
        topCategories: [],
        subcategories: [],
        currentCategory: null,
        currentTopCategory: null,
        searchQuery: '',
        instantSearchResults: [],
        instantSearchCategories: [],
        instantSearchPages: [],
        instantSearchOpen: false,
        instantSearchLoading: false,
        instantSearchTimeout: null,
        sortBy: '',
        priceMin: 0,
        priceMax: 500,
        priceSliderMin: 0,
        priceSliderMax: 500,
        priceFilterActive: false,
        page: 1,
        pageSize: 12,
        totalPages: 1,

        // Cart
        cartId: localStorage.getItem('cartId'),
        cart: {},
        cartCount: 0,
        couponCode: '',

        // Checkout
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
        countries: [],
        orderSummaryOpen: false,
        paymentMethods: [],
        paymentMethodsLoaded: false,
        lastOrderId: '',

        // Auth
        token: localStorage.getItem('authToken'),
        customer: JSON.parse(localStorage.getItem('customer') || 'null'),
        loginForm: { email: '', password: '' },
        registerForm: { firstName: '', lastName: '', email: '', password: '', confirmPassword: '' },
        accountTab: 'info',
        addresses: [],
        orders: [],
        ordersPage: 1,
        ordersTotalPages: 1,
        ordersTotalItems: 0,
        selectedOrder: null,
        selectedOrderInvoices: [],
        loadingInvoices: false,
        editingAddressId: null,
        newAddress: {
            firstName: '',
            lastName: '',
            street: '',
            city: '',
            postcode: '',
            region: '',
            regionId: null,
            countryId: 'AU',
            telephone: '',
            isDefaultBilling: false,
            isDefaultShipping: false
        },
        showAddressForm: false,

        // Profile & Password forms
        profileForm: { firstName: '', lastName: '', email: '' },
        passwordForm: { currentPassword: '', newPassword: '', confirmPassword: '' },
        forgotPasswordEmail: '',
        newsletterEmail: '',
        resetForm: { email: '', token: '', newPassword: '', confirmPassword: '' },

        // Wishlist
        wishlist: [],
        wishlistLoading: false,
        guestWishlist: JSON.parse(localStorage.getItem('guestWishlist') || '[]'),

        // Reviews
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

        // ==================== Module Loader ====================

        async loadModule(name) {
            if (loadedModules.has(name)) return;

            try {
                const module = await import(`/api-test/store/modules/${name}.js?v=42`);
                const methods = module.default;

                // Merge module methods into store (skip state, keep methods)
                for (const [key, value] of Object.entries(methods)) {
                    if (typeof value === 'function') {
                        this[key] = value.bind(this);
                    }
                }

                loadedModules.add(name);
                console.log(`Module loaded: ${name}`);
            } catch (e) {
                console.error(`Failed to load module ${name}:`, e);
            }
        },

        // ==================== Core Methods ====================

        async init() {
            // Validate auth state on init
            this.validateAuthState();

            // Load core modules immediately
            await Promise.all([
                this.loadModule('catalog'),
                this.loadModule('cart'),
                this.loadModule('product'),
                this.loadModule('wishlist'),
                this.loadModule('auth')
            ]);

            // Load initial data
            await Promise.all([
                this.loadCategories(),
                this.loadCart(),
                this.loadFooterPages(),
                this.loadStores()
            ]);

            // Resolve current path
            await this.resolveCurrentPath();

            // Handle browser back/forward
            window.addEventListener('popstate', () => {
                this.resolveCurrentPath();
            });
        },

        // Auth state validation (inline to avoid module dependency)
        validateAuthState() {
            if ((this.token && !this.customer) || (!this.token && this.customer)) {
                console.log('Auth state mismatch, clearing stale data');
                this.clearAuth();
            }
        },

        clearAuth() {
            this.token = null;
            this.customer = null;
            this.addresses = [];
            this.orders = [];
            this.editingAddressId = null;
            this.showAddressForm = false;
            localStorage.removeItem('authToken');
            localStorage.removeItem('customer');
        },

        // Newsletter toggle - inline implementation to avoid module timing issues
        async toggleNewsletter(subscribe) {
            if (!this.token || !this.customer?.email) {
                this.error = 'Please log in to manage newsletter subscription';
                return;
            }

            this.loading = true;
            this.error = null;
            try {
                const endpoint = subscribe ? '/api/newsletter/subscribe' : '/api/newsletter/unsubscribe';
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + this.token
                    },
                    body: JSON.stringify({ email: this.customer.email })
                });

                const data = await res.json();

                if (res.status === 401) {
                    this.clearAuth();
                    this.error = 'Session expired. Please log in again.';
                    return;
                }

                if (data.error) {
                    throw new Error(data.message || 'Newsletter operation failed');
                }

                if (this.customer) {
                    this.customer.isSubscribed = data.isSubscribed;
                    localStorage.setItem('customer', JSON.stringify(this.customer));
                }

                this.success = data.message || (subscribe ? 'Subscribed to newsletter' : 'Unsubscribed from newsletter');
            } catch (e) {
                this.error = e.message || 'Newsletter operation failed';
            }
            this.loading = false;
        },

        // Guest newsletter signup (footer form)
        async subscribeNewsletter() {
            if (!this.newsletterEmail) {
                this.error = 'Please enter your email address';
                return;
            }

            this.loading = true;
            this.error = null;
            try {
                const res = await fetch('/api/newsletter/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.newsletterEmail })
                });

                const data = await res.json();

                if (data.error) {
                    throw new Error(data.message || 'Subscription failed');
                }

                this.success = data.message || 'Successfully subscribed to newsletter!';
                this.newsletterEmail = '';
            } catch (e) {
                this.error = e.message || 'Newsletter subscription failed';
            }
            this.loading = false;
        },

        async loadFooterPages() {
            try {
                const data = await this.api('/cms-pages?isFooterLink=true');
                this.footerPages = data.member || data['hydra:member'] || data || [];
            } catch (e) {
                console.error('Failed to load footer pages:', e);
                this.footerPages = [];
            }
        },

        async loadBlogPosts() {
            try {
                const data = await this.api('/blog-posts?pageSize=10');
                this.blogPosts = data.member || data['hydra:member'] || data || [];
            } catch (e) {
                console.error('Failed to load blog posts:', e);
                this.blogPosts = [];
            }
        },

        async resolveCurrentPath() {
            const path = window.location.pathname.replace(BASE_PATH, '').replace(/^\/+/, '');
            const params = new URLSearchParams(window.location.search);
            this.currentPath = path;

            // Handle query params for SPA state
            if (params.get('search')) {
                this.searchQuery = params.get('search');
                this.page = parseInt(params.get('page')) || 1;
                this.pageSize = parseInt(params.get('pageSize')) || 12;
                this.sortBy = params.get('sort') || '';
                this.view = 'search';
                this.loadProducts();
                return;
            }

            if (params.get('product')) {
                await this.viewProduct(params.get('product'));
                return;
            }

            if (params.get('category')) {
                const categoryId = parseInt(params.get('category'));
                this.currentCategory = categoryId;
                this.currentTopCategory = categoryId;
                this.searchQuery = '';  // Clear search when loading category from URL
                this.page = parseInt(params.get('page')) || 1;
                this.pageSize = parseInt(params.get('pageSize')) || 12;
                this.sortBy = params.get('sort') || '';
                if (params.get('priceMin')) {
                    this.priceMin = parseInt(params.get('priceMin'));
                    this.priceFilterActive = true;
                }
                if (params.get('priceMax')) {
                    this.priceMax = parseInt(params.get('priceMax'));
                    this.priceFilterActive = true;
                }
                this.view = 'category';
                await this.loadSubcategories(categoryId);
                this.loadProducts();
                return;
            }

            // Handle path-based routing
            if (!path || path === '') {
                this.view = 'home';
                await this.loadHomePage();
                return;
            }

            if (path === 'cart') {
                this.view = 'cart';
                return;
            }

            if (path === 'checkout') {
                await this.loadModule('checkout');
                this.view = 'checkout';
                await this.loadCountries();
                await this.loadPaymentMethods();
                await this.prefillCheckoutFromAccount();
                return;
            }

            if (path === 'login') {
                await this.loadModule('auth');
                this.view = 'login';
                return;
            }

            if (path === 'register') {
                await this.loadModule('auth');
                this.view = 'register';
                return;
            }

            if (path === 'account') {
                await this.loadModule('auth');
                // Require authentication for account page
                if (!this.token) {
                    this.navigate('/login');
                    return;
                }
                this.view = 'account';
                await this.loadAccountData();
                return;
            }

            if (path === 'wishlist') {
                await this.loadModule('wishlist');
                if (this.token) {
                    await this.loadWishlist();
                }
                this.view = 'wishlist';
                return;
            }

            if (path === 'blog') {
                await this.loadBlogPosts();
                this.view = 'blog';
                return;
            }

            if (path.startsWith('blog/')) {
                const urlKey = path.replace('blog/', '');
                await this.viewBlogPost(urlKey);
                return;
            }

            // Try to resolve as CMS page or category URL
            try {
                const response = await this.api('/url-resolver?path=' + encodeURIComponent(path));
                // API returns Hydra collection with member array
                const results = response.member || response['hydra:member'] || (Array.isArray(response) ? response : []);
                const data = results[0];
                if (data?.type === 'cms_page') {
                    await this.loadCmsPage(data.id);
                    return;
                } else if (data?.type === 'category') {
                    this.currentCategory = data.id;
                    this.currentTopCategory = data.id;
                    this.page = parseInt(params.get('page')) || 1;
                    this.pageSize = parseInt(params.get('pageSize')) || 12;
                    this.sortBy = params.get('sort') || '';
                    this.view = 'category';
                    await this.loadSubcategories(data.id);
                    this.loadProducts();
                    return;
                } else if (data?.type === 'product') {
                    await this.viewProduct(data.id);
                    return;
                }
            } catch (e) {
                console.log('URL not found:', path);
            }

            this.view = '404';
        },

        async loadHomePage() {
            try {
                const data = await this.api('/cms-pages?identifier=home');
                const pages = data.member || data['hydra:member'] || data || [];
                if (pages.length > 0) {
                    this.cmsPage = pages[0];
                }
            } catch (e) {
                console.log('No home CMS page found');
            }
        },

        async loadCmsPage(pageId) {
            try {
                this.cmsPage = await this.api('/cms-pages/' + pageId);
                this.view = 'cms_page';
            } catch (e) {
                this.error = 'Page not found';
                this.view = '404';
            }
        },

        // ==================== URL & Navigation ====================

        buildUrl(params = {}) {
            const searchParams = new URLSearchParams();
            if (params.product) searchParams.set('product', params.product);
            if (params.category) searchParams.set('category', params.category);
            if (params.page > 1) searchParams.set('page', params.page);
            if (params.pageSize && params.pageSize != 12) searchParams.set('pageSize', params.pageSize);
            if (params.sort) searchParams.set('sort', params.sort);
            if (params.priceFilterActive && params.priceMin > 0) searchParams.set('priceMin', params.priceMin);
            if (params.priceFilterActive && params.priceMax < params.priceSliderMax) searchParams.set('priceMax', params.priceMax);
            if (params.search) searchParams.set('search', params.search);
            return searchParams.toString() ? '?' + searchParams.toString() : '';
        },

        updateUrl() {
            const params = this.buildUrl({
                product: this.view === 'product' && this.currentProduct ? this.currentProduct.id : null,
                category: this.view === 'category' && this.currentCategory ? this.currentCategory : null,
                page: this.page,
                pageSize: this.pageSize,
                sort: this.sortBy,
                priceMin: this.priceMin,
                priceMax: this.priceMax,
                priceFilterActive: this.priceFilterActive,
                priceSliderMax: this.priceSliderMax,
                search: this.view === 'search' ? this.searchQuery : null
            });
            const newUrl = BASE_PATH + params;
            window.history.replaceState({}, '', newUrl);
        },

        navigateTo(path, pushState = true) {
            const fullPath = path ? BASE_PATH + '/' + path : BASE_PATH;
            if (pushState) {
                window.history.pushState({}, '', fullPath);
            }
            this.resolveCurrentPath();
        },

        async navigate(view) {
            this.error = null;
            this.success = null;
            if (view === 'home') {
                this.currentCategory = null;
                this.currentTopCategory = null;
                this.subcategories = [];
                this.searchQuery = '';
                this.page = 1;
                this.sortBy = '';
                this.priceMin = this.priceSliderMin;
                this.priceMax = this.priceSliderMax;
                this.priceFilterActive = false;
                this.cmsPage = null;
                this.currentBlogPost = null;
                this.view = 'home';
                this.navigateTo('');
            } else if (view === 'blog') {
                this.currentBlogPost = null;
                this.view = 'blog';
                this.loadBlogPosts();
                window.history.pushState({}, '', BASE_PATH + '/blog');
            } else if (view === 'cart' || view === 'checkout' || view === 'login' || view === 'register' || view === 'account' || view === 'wishlist') {
                this.currentBlogPost = null;
                window.history.pushState({}, '', BASE_PATH + '/' + view);

                // Load required modules BEFORE setting view (so methods are available when template renders)
                if (view === 'checkout') {
                    await this.loadModule('checkout');
                    await this.loadCountries();
                    await this.loadPaymentMethods();
                    await this.prefillCheckoutFromAccount();
                }
                if (view === 'login' || view === 'register' || view === 'account') await this.loadModule('auth');
                if (view === 'wishlist') {
                    await this.loadModule('wishlist');
                    if (this.token) await this.loadWishlist();
                }

                this.view = view;
            } else {
                this.view = view;
            }
        },

        // ==================== API Helper ====================

        async api(endpoint, options = {}, retry = true) {
            const url = '/api' + endpoint;
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/ld+json',
                ...options.headers
            };

            // Only send token if it's not expired
            if (this.token && !this.isTokenExpired()) {
                headers['Authorization'] = 'Bearer ' + this.token;
            } else if (this.token && this.isTokenExpired()) {
                // Token expired - clear it
                this.clearExpiredToken();
            }

            if (this.currentStoreCode && this.currentStoreCode !== 'default') {
                headers['X-Store-Code'] = this.currentStoreCode;
            }

            const response = await fetch(url, { ...options, headers });

            // Handle 401 - clear token and retry for public endpoints
            if (response.status === 401 && retry && this.token) {
                this.clearExpiredToken();
                return this.api(endpoint, options, false); // Retry without token
            }

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || error.detail || 'API Error');
            }
            return response.json();
        },

        isTokenExpired() {
            const token = localStorage.getItem('authToken');
            if (!token) return true;
            try {
                // Decode JWT payload (middle segment)
                const payload = JSON.parse(atob(token.split('.')[1]));
                if (!payload.exp) return false;
                // exp is Unix timestamp in seconds
                return Date.now() > payload.exp * 1000;
            } catch {
                return false;
            }
        },

        clearExpiredToken() {
            this.token = null;
            this.customer = null;
            localStorage.removeItem('authToken');
            localStorage.removeItem('maho_api_auth');
        },

        // ==================== Store Switching ====================

        async loadStores() {
            try {
                const data = await this.api('/stores');
                this.stores = data.stores || data || [];
            } catch (e) {
                console.error('Failed to load stores:', e);
            }
        },

        async switchStore(storeCode) {
            this.currentStoreCode = storeCode;
            localStorage.setItem('storeCode', storeCode);
            await this.loadCategories();
            await this.loadProducts();
        },

        // ==================== Countries & Regions ====================

        async loadCountries() {
            if (this.countries.length > 0) return;
            try {
                const data = await this.api('/countries');
                this.countries = data.member || data || [];
            } catch (e) {
                console.error('Failed to load countries:', e);
                this.countries = [{
                    id: 'AU',
                    name: 'Australia',
                    available_regions: [
                        { id: 320, code: 'ACT', name: 'Australian Capital Territory' },
                        { id: 321, code: 'NSW', name: 'New South Wales' },
                        { id: 322, code: 'NT', name: 'Northern Territory' },
                        { id: 323, code: 'QLD', name: 'Queensland' },
                        { id: 324, code: 'SA', name: 'South Australia' },
                        { id: 325, code: 'TAS', name: 'Tasmania' },
                        { id: 326, code: 'VIC', name: 'Victoria' },
                        { id: 327, code: 'WA', name: 'Western Australia' }
                    ]
                }];
            }
        },

        get currentCountryRegions() {
            const country = this.countries.find(c => c.id === this.checkout.shipping.countryId);
            return country?.available_regions || [];
        },

        onRegionChange(event) {
            const regionId = parseInt(event.target.value);
            const region = this.currentCountryRegions.find(r => r.id === regionId);
            if (region) {
                this.checkout.shipping.regionId = region.id;
                this.checkout.shipping.region = region.code;
            } else {
                this.checkout.shipping.regionId = null;
                this.checkout.shipping.region = '';
            }
        },

        onCountryChange() {
            this.checkout.shipping.regionId = null;
            this.checkout.shipping.region = '';
        },

        get newAddressRegions() {
            const country = this.countries.find(c => c.id === this.newAddress.countryId);
            return country?.available_regions || [];
        },

        // ==================== Blog ====================

        async viewBlogPost(urlKeyOrId) {
            this.loading = true;
            try {
                let post;
                if (!isNaN(urlKeyOrId)) {
                    post = await this.api('/blog-posts/' + urlKeyOrId);
                } else {
                    const data = await this.api('/blog-posts?urlKey=' + encodeURIComponent(urlKeyOrId));
                    const posts = data.member || data['hydra:member'] || data || [];
                    post = posts.find(p => p.urlKey === urlKeyOrId) || posts[0];
                }
                if (post) {
                    this.currentBlogPost = post;
                    this.view = 'blog_post';
                    const newPath = BASE_PATH + '/blog/' + (post.urlKey || post.id);
                    if (window.location.pathname !== newPath) {
                        window.history.pushState({}, '', newPath);
                    }
                }
            } catch (e) {
                this.error = 'Blog post not found';
            }
            this.loading = false;
        },

        // ==================== Computed Properties ====================

        get pageTitle() {
            if (this.view === 'cms_page' && this.cmsPage) {
                return this.cmsPage.title;
            }
            if (this.view === 'search' && this.searchQuery) {
                return `Search: "${this.searchQuery}"`;
            }
            if (this.view === 'category' && this.currentCategory) {
                const subcat = this.subcategories.find(c => c.id == this.currentCategory);
                if (subcat) return subcat.name;
                const cat = this.categories.find(c => c.id == this.currentCategory);
                return cat ? cat.name : 'Category';
            }
            if (this.view === 'product' && this.currentProduct) {
                return this.currentProduct.name;
            }
            if (this.view === 'blog_post' && this.currentBlogPost) {
                return this.currentBlogPost.title;
            }
            const titles = {
                home: 'Welcome',
                cart: 'Shopping Cart',
                checkout: 'Checkout',
                login: 'Login',
                register: 'Create Account',
                account: 'My Account',
                blog: 'Blog',
                success: 'Order Confirmed',
                '404': 'Page Not Found'
            };
            return titles[this.view] || 'Store';
        },

        // ==================== Utility ====================

        formatPrice(price) {
            return '$' + (parseFloat(price) || 0).toFixed(2);
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString('en-AU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        },

        // ==================== Instant Search ====================

        onSearchInput() {
            // Clear previous timeout
            if (this.instantSearchTimeout) {
                clearTimeout(this.instantSearchTimeout);
            }

            // Require minimum 2 characters
            if (this.searchQuery.length < 2) {
                this.instantSearchResults = [];
                this.instantSearchOpen = false;
                return;
            }

            // Debounce: wait 300ms after user stops typing
            this.instantSearchTimeout = setTimeout(() => {
                this.performInstantSearch();
            }, 300);
        },

        async performInstantSearch() {
            if (this.searchQuery.length < 2) return;

            this.instantSearchLoading = true;
            this.instantSearchOpen = true;

            const query = encodeURIComponent(this.searchQuery);

            // Fetch products, categories, and pages in parallel
            try {
                const [productsData, categoriesData, pagesData] = await Promise.all([
                    this.api('/products?search=' + query + '&pageSize=12').catch(() => ({})),
                    this.api('/categories?search=' + query + '&pageSize=8').catch(() => ({})),
                    this.api('/cms-pages?search=' + query + '&pageSize=6').catch(() => ({}))
                ]);

                this.instantSearchResults = productsData.member || productsData['hydra:member'] || productsData.items || [];
                this.instantSearchCategories = categoriesData.member || categoriesData['hydra:member'] || categoriesData.items || [];
                this.instantSearchPages = pagesData.member || pagesData['hydra:member'] || pagesData.items || [];
            } catch (e) {
                console.error('Instant search failed:', e);
                this.instantSearchResults = [];
                this.instantSearchCategories = [];
                this.instantSearchPages = [];
            }

            this.instantSearchLoading = false;
        },

        selectSearchResult(product) {
            this.instantSearchOpen = false;
            this.clearInstantSearchResults();
            this.viewProduct(product.id);
        },

        selectCategory(category) {
            this.instantSearchOpen = false;
            this.clearInstantSearchResults();
            this.searchQuery = '';
            // Navigate to category via URL parameter
            window.history.pushState({}, '', BASE_PATH + '?category=' + category.id);
            this.resolveCurrentPath();
        },

        selectPage(page) {
            this.instantSearchOpen = false;
            this.clearInstantSearchResults();
            this.searchQuery = '';
            // Navigate to CMS page via identifier (URL resolver handles it)
            window.history.pushState({}, '', BASE_PATH + '/' + page.identifier);
            this.resolveCurrentPath();
        },

        clearInstantSearchResults() {
            this.instantSearchResults = [];
            this.instantSearchCategories = [];
            this.instantSearchPages = [];
        },

        closeInstantSearch() {
            // Small delay to allow click events on results
            setTimeout(() => {
                this.instantSearchOpen = false;
            }, 200);
        },

        submitSearch() {
            this.instantSearchOpen = false;
            this.clearInstantSearchResults();
            if (this.searchQuery.trim()) {
                this.searchProducts();
            }
        }
    };
}
