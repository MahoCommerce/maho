/**
 * SEO Module - Meta tags, Open Graph, canonical URLs, and JSON-LD structured data
 *
 * All branding (site name, logo, default descriptions) is sourced from storeConfig
 * which comes from the Maho backend — no hardcoded store values.
 */

const BASE_URL = window.location.origin;
const BASE_PATH = '/api-test/store';

function setMeta(name, content) {
    let el = document.querySelector(`meta[name="${name}"]`);
    if (!el) {
        el = document.createElement('meta');
        el.setAttribute('name', name);
        document.head.appendChild(el);
    }
    el.setAttribute('content', content || '');
}

function setMetaProperty(property, content) {
    let el = document.querySelector(`meta[property="${property}"]`);
    if (!el) {
        el = document.createElement('meta');
        el.setAttribute('property', property);
        document.head.appendChild(el);
    }
    el.setAttribute('content', content || '');
}

function setCanonical(url) {
    let el = document.querySelector('link[rel="canonical"]');
    if (!el) {
        el = document.createElement('link');
        el.setAttribute('rel', 'canonical');
        document.head.appendChild(el);
    }
    el.setAttribute('href', url);
}

function injectJsonLd(data) {
    let el = document.getElementById('seo-json-ld');
    if (!el) {
        el = document.createElement('script');
        el.id = 'seo-json-ld';
        el.type = 'application/ld+json';
        document.head.appendChild(el);
    }
    el.textContent = JSON.stringify(data);
}

function truncate(text, maxLength) {
    if (!text) return '';
    const stripped = text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    if (stripped.length <= maxLength) return stripped;
    return stripped.substring(0, maxLength - 3).replace(/\s+\S*$/, '') + '...';
}

function getCanonicalUrl() {
    return BASE_URL + window.location.pathname;
}

function getProductSchema(product, currency) {
    const schema = {
        '@context': 'https://schema.org',
        '@type': 'Product',
        'name': product.name,
        'description': truncate(product.description || product.shortDescription, 500),
        'sku': product.sku,
        'url': getCanonicalUrl()
    };

    if (product.imageUrl) {
        schema.image = product.imageUrl;
    }

    if (product.finalPrice || product.price) {
        schema.offers = {
            '@type': 'Offer',
            'price': product.finalPrice || product.price,
            'priceCurrency': currency || 'AUD',
            'availability': product.stockStatus === 'in_stock'
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock'
        };
    }

    if (product.reviewCount > 0 && product.averageRating) {
        schema.aggregateRating = {
            '@type': 'AggregateRating',
            'ratingValue': product.averageRating,
            'bestRating': 5,
            'reviewCount': product.reviewCount
        };
    }

    return schema;
}

function getBreadcrumbSchema(crumbs) {
    return {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        'itemListElement': crumbs.map((crumb, i) => ({
            '@type': 'ListItem',
            'position': i + 1,
            'name': crumb.name,
            'item': crumb.url ? BASE_URL + BASE_PATH + '/' + crumb.url : undefined
        }))
    };
}

function getOrganizationSchema(siteName, logoUrl) {
    const schema = {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        'name': siteName,
        'url': BASE_URL + BASE_PATH
    };
    if (logoUrl) {
        schema.logo = logoUrl;
    }
    return schema;
}

export default {
    updateSeo() {
        const view = this.view;
        const cfg = this.storeConfig || {};
        const siteName = cfg.defaultTitle || cfg.storeName || 'Store';
        const siteDescription = cfg.defaultDescription || '';
        const currency = cfg.defaultDisplayCurrencyCode || cfg.baseCurrencyCode || 'AUD';
        const logoUrl = cfg.logoUrl || null;

        let title = '';
        let description = '';
        let image = '';
        let ogType = 'website';
        let jsonLd = null;

        if (view === 'product' && this.currentProduct?.name) {
            const p = this.currentProduct;
            title = p.metaTitle || p.name;
            description = p.metaDescription || truncate(p.shortDescription || p.description, 160);
            image = p.imageUrl || '';
            ogType = 'product';
            jsonLd = getProductSchema(p, currency);

            // Build breadcrumbs from category
            if (p.categoryIds?.length && this.categories?.length) {
                const cat = this.categories.find(c => p.categoryIds.includes(c.id));
                if (cat) {
                    const crumbs = [
                        { name: 'Home', url: '' },
                        { name: cat.name, url: cat.urlKey || cat.id },
                        { name: p.name }
                    ];
                    jsonLd = [jsonLd, getBreadcrumbSchema(crumbs)];
                }
            }

            setMeta('keywords', p.metaKeywords || '');
        } else if (view === 'category' && this.currentCategory) {
            const cat = this.subcategories?.find(c => c.id == this.currentCategory)
                || this.categories?.find(c => c.id == this.currentCategory);
            if (cat) {
                title = cat.metaTitle || cat.name;
                description = cat.metaDescription || truncate(cat.description, 160);
                setMeta('keywords', cat.metaKeywords || '');

                const crumbs = [
                    { name: 'Home', url: '' },
                    { name: cat.name }
                ];
                jsonLd = getBreadcrumbSchema(crumbs);
            } else {
                title = 'Category';
            }
        } else if (view === 'cms_page' && this.cmsPage) {
            const page = this.cmsPage;
            title = page.title;
            description = page.metaDescription || truncate(page.content, 160);
            setMeta('keywords', page.metaKeywords || '');
        } else if (view === 'blog_post' && this.currentBlogPost) {
            const post = this.currentBlogPost;
            title = post.metaTitle || post.title;
            description = post.metaDescription || truncate(post.excerpt || post.content, 160);
            image = post.imageUrl || '';
            ogType = 'article';
        } else if (view === 'search' && this.searchQuery) {
            title = `Search: "${this.searchQuery}"`;
            description = `Search results for "${this.searchQuery}" at ${siteName}`;
        } else if (view === 'home') {
            title = siteName;
            description = siteDescription;
            jsonLd = getOrganizationSchema(siteName, logoUrl);
        } else {
            const titles = {
                cart: 'Shopping Cart',
                checkout: 'Checkout',
                login: 'Login',
                register: 'Create Account',
                account: 'My Account',
                blog: 'Blog',
                wishlist: 'Wishlist',
                success: 'Order Confirmed',
                '404': 'Page Not Found'
            };
            title = titles[view] || 'Store';
        }

        // Set document title
        document.title = view === 'home' ? title : `${title} | ${siteName}`;

        // Meta description
        setMeta('description', description);

        // Canonical URL
        setCanonical(getCanonicalUrl());

        // Open Graph
        setMetaProperty('og:title', title);
        setMetaProperty('og:description', description);
        setMetaProperty('og:image', image);
        setMetaProperty('og:url', getCanonicalUrl());
        setMetaProperty('og:type', ogType);
        setMetaProperty('og:site_name', siteName);

        // Twitter Card
        setMeta('twitter:card', image ? 'summary_large_image' : 'summary');
        setMeta('twitter:title', title);
        setMeta('twitter:description', description);
        setMeta('twitter:image', image);

        // JSON-LD structured data
        if (jsonLd) {
            injectJsonLd(jsonLd);
        } else {
            const el = document.getElementById('seo-json-ld');
            if (el) el.textContent = '';
        }
    }
};
