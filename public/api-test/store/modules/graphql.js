/**
 * GraphQL Module - Query and mutation string constants for all API operations
 *
 * API Platform naming: appends resource class name as suffix to operation names.
 * API Platform mutations: always use input objects, not inline args.
 * API Platform collections: use Relay cursor pagination (first/last/before/after).
 * Custom queries with explicit args in ApiResource DO accept those args directly.
 */

// ==================== Catalog ====================

const CATEGORIES_QUERY = `
query GetCategories($parentId: Int, $includeInMenu: Boolean) {
    categoriesCategories(parentId: $parentId, includeInMenu: $includeInMenu) {
        edges { node { id parentId name urlKey urlPath image level position isActive includeInMenu productCount childrenIds } }
        totalCount
    }
}`;

const CATEGORY_QUERY = `
query GetCategory($id: ID!) {
    categoryCategory(id: $id) {
        id parentId name description urlKey urlPath image level position isActive includeInMenu productCount childrenIds
    }
}`;

const PRODUCTS_QUERY = `
query GetProducts($search: String, $categoryId: Int, $priceMin: Float, $priceMax: Float, $sortBy: String, $sortDir: String, $pageSize: Int, $page: Int) {
    productsProducts(search: $search, categoryId: $categoryId, priceMin: $priceMin, priceMax: $priceMax, sortBy: $sortBy, sortDir: $sortDir, pageSize: $pageSize, page: $page) {
        edges { node { id sku urlKey name type status finalPrice price specialPrice stockStatus stockQty imageUrl smallImageUrl thumbnailUrl reviewCount averageRating } }
        totalCount
        pageInfo { hasNextPage endCursor }
    }
}`;

// ==================== Product ====================

const PRODUCT_QUERY = `
query GetProduct($id: ID!) {
    productProduct(id: $id) {
        id sku urlKey name description shortDescription type status visibility
        price specialPrice finalPrice stockStatus stockQty weight
        imageUrl smallImageUrl thumbnailUrl categoryIds
        reviewCount averageRating hasRequiredOptions
        configurableOptions variants customOptions mediaGallery
        relatedProducts crosssellProducts upsellProducts
        downloadableLinks linksTitle linksPurchasedSeparately
        groupedProducts bundleOptions
    }
}`;

// ==================== Cart ====================

const CREATE_CART = `
mutation CreateCart($input: createCartCartInput!) {
    createCartCart(input: $input) { cart { id maskedId } }
}`;

const CART_QUERY = `
query GetCart($maskedId: String!) {
    getCartByMaskedIdCart(maskedId: $maskedId) {
        id maskedId isActive itemsCount itemsQty
        items prices appliedCoupon
        availableShippingMethods selectedShippingMethod
        availablePaymentMethods selectedPaymentMethod
        billingAddress { firstName lastName street city region regionId postcode countryId telephone }
        shippingAddress { firstName lastName street city region regionId postcode countryId telephone }
    }
}`;

const ADD_TO_CART = `
mutation AddToCart($input: addToCartCartInput!) {
    addToCartCart(input: $input) {
        cart {
            id itemsCount itemsQty items prices appliedCoupon
        }
    }
}`;

const UPDATE_CART_ITEM = `
mutation UpdateCartItemQty($input: updateCartItemQtyCartInput!) {
    updateCartItemQtyCart(input: $input) {
        cart {
            id itemsCount itemsQty items prices appliedCoupon
        }
    }
}`;

const REMOVE_CART_ITEM = `
mutation RemoveCartItem($input: removeCartItemCartInput!) {
    removeCartItemCart(input: $input) {
        cart {
            id itemsCount itemsQty items prices appliedCoupon
        }
    }
}`;

const APPLY_COUPON = `
mutation ApplyCouponToCart($input: applyCouponToCartCartInput!) {
    applyCouponToCartCart(input: $input) {
        cart {
            id appliedCoupon items prices
        }
    }
}`;

const REMOVE_COUPON = `
mutation RemoveCouponFromCart($input: removeCouponFromCartCartInput!) {
    removeCouponFromCartCart(input: $input) {
        cart {
            id appliedCoupon prices
        }
    }
}`;

const SET_SHIPPING_ADDRESS = `
mutation SetShippingAddressOnCart($input: setShippingAddressOnCartCartInput!) {
    setShippingAddressOnCartCart(input: $input) {
        cart {
            id availableShippingMethods selectedShippingMethod
            shippingAddress { firstName lastName street city region regionId postcode countryId telephone }
        }
    }
}`;

const SET_SHIPPING_METHOD = `
mutation SetShippingMethodOnCart($input: setShippingMethodOnCartCartInput!) {
    setShippingMethodOnCartCart(input: $input) {
        cart {
            id selectedShippingMethod prices
        }
    }
}`;

const SET_PAYMENT_METHOD = `
mutation SetPaymentMethodOnCart($input: setPaymentMethodOnCartCartInput!) {
    setPaymentMethodOnCartCart(input: $input) {
        cart {
            id selectedPaymentMethod availablePaymentMethods
        }
    }
}`;

// ==================== Checkout / Order ====================

const PLACE_ORDER = `
mutation PlaceOrder($input: placeOrderOrderInput!) {
    placeOrderOrder(input: $input) {
        order {
            id incrementId status customerEmail
            prices
        }
    }
}`;

const COUNTRIES_QUERY = `
query GetCountries {
    countriesCountries(first: 300) {
        edges { node { id name iso2Code iso3Code availableRegions } }
        totalCount
    }
}`;

// ==================== Customer / Auth ====================

const ME_QUERY = `
query Me {
    meCustomer {
        id email firstName lastName fullName isSubscribed groupId
        defaultBillingAddress { id firstName lastName street city region regionId postcode countryId telephone company isDefaultBilling isDefaultShipping }
        defaultShippingAddress { id firstName lastName street city region regionId postcode countryId telephone company isDefaultBilling isDefaultShipping }
        addresses
    }
}`;

const MY_ADDRESSES_QUERY = `
query MyAddresses {
    myAddressesAddresses {
        edges { node { id firstName lastName company street city region regionId postcode countryId telephone isDefaultBilling isDefaultShipping } }
        totalCount
    }
}`;

const CREATE_ADDRESS = `
mutation CreateAddress($input: createAddressAddressInput!) {
    createAddressAddress(input: $input) {
        address { id firstName lastName company street city region regionId postcode countryId telephone isDefaultBilling isDefaultShipping }
    }
}`;

const UPDATE_ADDRESS = `
mutation UpdateAddress($input: updateAddressAddressInput!) {
    updateAddressAddress(input: $input) {
        address { id firstName lastName company street city region regionId postcode countryId telephone isDefaultBilling isDefaultShipping }
    }
}`;

const DELETE_ADDRESS = `
mutation DeleteAddress($input: deleteAddressAddressInput!) {
    deleteAddressAddress(input: $input) { address { id } }
}`;

const CUSTOMER_ORDERS_QUERY = `
query CustomerOrders($page: Int, $pageSize: Int, $status: String) {
    customerOrdersOrders(page: $page, pageSize: $pageSize, status: $status) {
        edges {
            node {
                id incrementId status state customerEmail
                createdAt
                totalItemCount totalQtyOrdered
                shippingDescription paymentMethodTitle paymentMethod couponCode
                items
                prices
                shippingAddress { firstName lastName street city region postcode countryId telephone }
                billingAddress { firstName lastName street city region postcode countryId telephone }
            }
        }
        totalCount
    }
}`;

const UPDATE_CUSTOMER = `
mutation UpdateCustomer($input: updateCustomerCustomerInput!) {
    updateCustomerCustomer(input: $input) {
        customer { id email firstName lastName fullName isSubscribed }
    }
}`;

const CHANGE_PASSWORD = `
mutation ChangePassword($input: changePasswordCustomerInput!) {
    changePasswordCustomer(input: $input) {
        customer { id email }
    }
}`;

// ==================== Wishlist ====================

const MY_WISHLIST_QUERY = `
query MyWishlist {
    myWishlistWishlistItems(first: 100) {
        edges { node { id productId productName productSku productPrice productImageUrl productType qty addedAt inStock } }
        totalCount
    }
}`;

const ADD_TO_WISHLIST = `
mutation AddToWishlist($input: addToWishlistWishlistItemInput!) {
    addToWishlistWishlistItem(input: $input) {
        wishlistItem { id productId productName productSku productPrice productImageUrl productType qty addedAt inStock }
    }
}`;

const REMOVE_FROM_WISHLIST = `
mutation RemoveFromWishlist($input: removeFromWishlistWishlistItemInput!) {
    removeFromWishlistWishlistItem(input: $input) { wishlistItem { id } }
}`;

const SYNC_WISHLIST = `
mutation SyncWishlist($input: syncWishlistWishlistItemInput!) {
    syncWishlistWishlistItem(input: $input) { wishlistItem { id productId } }
}`;

const MOVE_WISHLIST_TO_CART = `
mutation MoveWishlistItemToCart($input: moveWishlistItemToCartWishlistItemInput!) {
    moveWishlistItemToCartWishlistItem(input: $input) {
        wishlistItem { id }
    }
}`;

// ==================== Reviews ====================

const PRODUCT_REVIEWS_QUERY = `
query ProductReviews($productId: Int!, $page: Int, $pageSize: Int) {
    productReviewsReviews(productId: $productId, page: $page, pageSize: $pageSize) {
        edges { node { id productId title detail nickname rating status createdAt } }
        totalCount
    }
}`;

const SUBMIT_REVIEW = `
mutation SubmitReview($input: submitReviewReviewInput!) {
    submitReviewReview(input: $input) {
        review { id productId title detail nickname rating status createdAt }
    }
}`;

const MY_REVIEWS_QUERY = `
query MyReviews {
    myReviewsReviews(first: 100) {
        edges { node { id productId productName title detail nickname rating status createdAt } }
        totalCount
    }
}`;

// ==================== CMS / Blog ====================

const CMS_PAGES_QUERY = `
query GetCmsPages {
    cmsPages(first: 100) {
        edges { node { id identifier title contentHeading content status } }
        totalCount
    }
}`;

const CMS_PAGE_QUERY = `
query GetCmsPage($id: ID!) {
    cmsPage(id: $id) {
        id identifier title contentHeading content metaKeywords metaDescription status
    }
}`;

const CMS_PAGE_BY_IDENTIFIER = `
query GetCmsPageByIdentifier($identifier: String!) {
    cmsPagesCmsPages(identifier: $identifier) {
        edges { node { id identifier title contentHeading content metaKeywords metaDescription status } }
    }
}`;

const BLOG_POSTS_QUERY = `
query GetBlogPosts {
    blogPosts(first: 20) {
        edges { node { id title urlKey excerpt imageUrl publishDate status } }
        totalCount
    }
}`;

const BLOG_POST_QUERY = `
query GetBlogPost($id: ID!) {
    blogPost(id: $id) {
        id title urlKey content excerpt imageUrl publishDate metaTitle metaDescription status
    }
}`;

const BLOG_POST_BY_URL_KEY = `
query GetBlogPostByUrlKey($urlKey: String!) {
    blogPostsBlogPosts(urlKey: $urlKey) {
        edges { node { id title urlKey content excerpt imageUrl publishDate metaTitle metaDescription status } }
    }
}`;

// ==================== Newsletter ====================

const SUBSCRIBE_NEWSLETTER = `
mutation SubscribeNewsletter($input: subscribeNewsletterNewsletterSubscriptionInput!) {
    subscribeNewsletterNewsletterSubscription(input: $input) { newsletterSubscription { email isSubscribed status message } }
}`;

const UNSUBSCRIBE_NEWSLETTER = `
mutation UnsubscribeNewsletter($input: unsubscribeNewsletterNewsletterSubscriptionInput!) {
    unsubscribeNewsletterNewsletterSubscription(input: $input) { newsletterSubscription { email isSubscribed status message } }
}`;

// ==================== Export ====================

export default {
    queries: {
        // Catalog
        CATEGORIES_QUERY,
        CATEGORY_QUERY,
        PRODUCTS_QUERY,
        // Product
        PRODUCT_QUERY,
        // Cart
        CREATE_CART,
        CART_QUERY,
        ADD_TO_CART,
        UPDATE_CART_ITEM,
        REMOVE_CART_ITEM,
        APPLY_COUPON,
        REMOVE_COUPON,
        SET_SHIPPING_ADDRESS,
        SET_SHIPPING_METHOD,
        SET_PAYMENT_METHOD,
        // Checkout / Order
        PLACE_ORDER,
        COUNTRIES_QUERY,
        CUSTOMER_ORDERS_QUERY,
        // Customer / Auth
        ME_QUERY,
        MY_ADDRESSES_QUERY,
        CREATE_ADDRESS,
        UPDATE_ADDRESS,
        DELETE_ADDRESS,
        UPDATE_CUSTOMER,
        CHANGE_PASSWORD,
        // Wishlist
        MY_WISHLIST_QUERY,
        ADD_TO_WISHLIST,
        REMOVE_FROM_WISHLIST,
        SYNC_WISHLIST,
        MOVE_WISHLIST_TO_CART,
        // Reviews
        PRODUCT_REVIEWS_QUERY,
        SUBMIT_REVIEW,
        MY_REVIEWS_QUERY,
        // CMS / Blog
        CMS_PAGES_QUERY,
        CMS_PAGE_QUERY,
        CMS_PAGE_BY_IDENTIFIER,
        BLOG_POSTS_QUERY,
        BLOG_POST_QUERY,
        BLOG_POST_BY_URL_KEY,
        // Newsletter
        SUBSCRIBE_NEWSLETTER,
        UNSUBSCRIBE_NEWSLETTER
    }
};
