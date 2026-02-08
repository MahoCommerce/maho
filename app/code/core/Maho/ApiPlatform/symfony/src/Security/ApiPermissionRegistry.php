<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Security;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;

/**
 * Central registry for API resource permissions.
 *
 * Single source of truth for resource definitions, REST path mappings,
 * and GraphQL field-to-resource resolution. Used by ApiUserVoter (REST),
 * GraphQlPermissionListener (GraphQL), and the admin role editor UI.
 */
class ApiPermissionRegistry
{
    /**
     * Resource definitions with available operations and labels.
     * Format: 'resource' => ['label' => string, 'operations' => ['op' => 'Label', ...]]
     */
    private const RESOURCES = [
        'products'   => ['label' => 'Products', 'operations' => ['read' => 'View']],
        'categories' => ['label' => 'Categories', 'operations' => ['read' => 'View']],
        'orders'     => ['label' => 'Orders', 'operations' => ['read' => 'View', 'create' => 'Place', 'write' => 'Manage']],
        'customers'  => ['label' => 'Customers', 'operations' => ['read' => 'View', 'create' => 'Register', 'write' => 'Update']],
        'carts'      => ['label' => 'Carts', 'operations' => ['read' => 'View', 'write' => 'Create & Modify']],
        'addresses'  => ['label' => 'Addresses', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'wishlists'  => ['label' => 'Wishlists', 'operations' => ['read' => 'View', 'write' => 'Add/Remove']],
        'reviews'    => ['label' => 'Reviews', 'operations' => ['read' => 'View', 'write' => 'Submit']],
        'shipments'  => ['label' => 'Shipments', 'operations' => ['read' => 'View', 'create' => 'Create']],
        'giftcards'  => ['label' => 'Gift Cards', 'operations' => ['read' => 'Check Balance', 'create' => 'Create', 'write' => 'Adjust Balance']],
        'newsletter' => ['label' => 'Newsletter', 'operations' => ['read' => 'View Status', 'write' => 'Subscribe/Unsubscribe']],
        'cms'        => ['label' => 'CMS Pages & Blocks', 'operations' => ['read' => 'View']],
        'blog'       => ['label' => 'Blog Posts', 'operations' => ['read' => 'View']],
        'stores'     => ['label' => 'Store Config', 'operations' => ['read' => 'View']],
        'countries'  => ['label' => 'Countries', 'operations' => ['read' => 'View']],
        'url-resolver' => ['label' => 'URL Resolver', 'operations' => ['read' => 'Resolve']],
        'pos'        => ['label' => 'POS', 'operations' => ['read' => 'View', 'write' => 'Manage']],
    ];

    /**
     * REST URL prefix → resource name
     */
    private const REST_PATH_MAP = [
        '/api/products'      => 'products',
        '/api/categories'    => 'categories',
        '/api/orders'        => 'orders',
        '/api/customers'     => 'customers',
        '/api/carts'         => 'carts',
        '/api/guest-carts'   => 'carts',
        '/api/addresses'     => 'addresses',
        '/api/wishlists'     => 'wishlists',
        '/api/wishlist'      => 'wishlists',
        '/api/reviews'       => 'reviews',
        '/api/giftcards'     => 'giftcards',
        '/api/newsletter'    => 'newsletter',
        '/api/cms-pages'     => 'cms',
        '/api/cms-blocks'    => 'cms',
        '/api/blog-posts'    => 'blog',
        '/api/stores'        => 'stores',
        '/api/store-config'  => 'stores',
        '/api/countries'     => 'countries',
        '/api/url-resolver'  => 'url-resolver',
        '/api/pos-payments'  => 'pos',
        '/api/shipments'     => 'shipments',
    ];

    /**
     * GraphQL field name → resource name.
     * Built from the API Platform shortName and operation names.
     */
    private const GRAPHQL_FIELD_MAP = [
        // Products
        'product'            => 'products',
        'products'           => 'products',
        'productBySku'       => 'products',
        'productByBarcode'   => 'products',
        'categoryProducts'   => 'products',
        // Categories
        'category'           => 'categories',
        'categories'         => 'categories',
        'categoryByUrlKey'   => 'categories',
        // Orders
        'order'              => 'orders',
        'orders'             => 'orders',
        'guestOrder'         => 'orders',
        'customerOrders'     => 'orders',
        'placeOrder'         => 'orders',
        'cancelOrder'        => 'orders',
        'placeOrderWithSplitPayments' => 'orders',
        'recordPayment'      => 'orders',
        'orderPayments'      => 'orders',
        'orderPaymentSummary' => 'orders',
        // Customers
        'customer'           => 'customers',
        'customers'          => 'customers',
        'me'                 => 'customers',
        'customerLogin'      => 'customers',
        'customerLogout'     => 'customers',
        'createCustomerQuick' => 'customers',
        'updateCustomer'     => 'customers',
        'changePassword'     => 'customers',
        'forgotPassword'     => 'customers',
        'resetPassword'      => 'customers',
        // Carts
        'cart'               => 'carts',
        'carts'              => 'carts',
        'customerCart'       => 'carts',
        'getCartByMaskedId'  => 'carts',
        'createCart'         => 'carts',
        'addToCart'          => 'carts',
        'updateCartItemQty'  => 'carts',
        'setCartItemFulfillment' => 'carts',
        'removeCartItem'     => 'carts',
        'applyCouponToCart'  => 'carts',
        'removeCouponFromCart' => 'carts',
        'setShippingAddressOnCart' => 'carts',
        'setBillingAddressOnCart'  => 'carts',
        'setShippingMethodOnCart'  => 'carts',
        'setPaymentMethodOnCart'   => 'carts',
        'assignCustomerToCart'     => 'carts',
        'applyGiftcardToCart'      => 'carts',
        'removeGiftcardFromCart'   => 'carts',
        // Addresses
        'address'            => 'addresses',
        'addresses'          => 'addresses',
        'myAddresses'        => 'addresses',
        'createAddress'      => 'addresses',
        'updateAddress'      => 'addresses',
        'deleteAddress'      => 'addresses',
        // Wishlists
        'wishlistItem'       => 'wishlists',
        'wishlistItems'      => 'wishlists',
        'myWishlist'         => 'wishlists',
        'addToWishlist'      => 'wishlists',
        'removeFromWishlist' => 'wishlists',
        'moveWishlistItemToCart' => 'wishlists',
        'syncWishlist'       => 'wishlists',
        // Reviews
        'review'             => 'reviews',
        'reviews'            => 'reviews',
        'productReviews'     => 'reviews',
        'myReviews'          => 'reviews',
        'submitReview'       => 'reviews',
        // Shipments
        'shipment'           => 'shipments',
        'shipments'          => 'shipments',
        'orderShipments'     => 'shipments',
        'createShipment'     => 'shipments',
        // Gift Cards
        'giftCard'           => 'giftcards',
        'giftCards'          => 'giftcards',
        'checkGiftcardBalance' => 'giftcards',
        'createGiftcard'     => 'giftcards',
        'adjustGiftcardBalance' => 'giftcards',
        // Newsletter
        'newsletterSubscription' => 'newsletter',
        'newsletterSubscriptions' => 'newsletter',
        'newsletterStatus'   => 'newsletter',
        'subscribeNewsletter'   => 'newsletter',
        'unsubscribeNewsletter' => 'newsletter',
        // CMS
        'cmsPage'            => 'cms',
        'cmsPages'           => 'cms',
        'cmsBlock'           => 'cms',
        'cmsBlocks'          => 'cms',
        'cmsBlockByIdentifier' => 'cms',
        // Blog
        'blogPost'           => 'blog',
        'blogPosts'          => 'blog',
        // Stores
        'storeConfig'        => 'stores',
        // Countries
        'country'            => 'countries',
        'countries'          => 'countries',
        // URL Resolver
        'urlResolveResult'   => 'url-resolver',
        'resolveUrl'         => 'url-resolver',
        // POS
        'posPayment'         => 'pos',
        'orderPosPayments'   => 'pos',
    ];

    /**
     * Mutation field name prefixes that map to the 'create' operation
     */
    private const CREATE_PREFIXES = ['place', 'create', 'register', 'submit', 'subscribe'];

    /**
     * Get full resource definitions for admin UI
     *
     * @return array<string, array{label: string, operations: array<string, string>}>
     */
    public function getResources(): array
    {
        return self::RESOURCES;
    }

    /**
     * Get REST path → resource map
     *
     * @return array<string, string>
     */
    public function getRestPathMap(): array
    {
        return self::REST_PATH_MAP;
    }

    /**
     * Resolve a REST URL path to a resource name
     */
    public function resolveRestResource(string $path): ?string
    {
        foreach (self::REST_PATH_MAP as $prefix => $resource) {
            if (str_starts_with($path, $prefix)) {
                return $resource;
            }
        }
        return null;
    }

    /**
     * Parse a GraphQL query and return required resource/operation pairs.
     *
     * @return array<string> List of permissions needed, e.g. ['products/read', 'orders/create']
     */
    public function resolveGraphQlPermissions(string $query): array
    {
        try {
            $document = Parser::parse($query);
        } catch (\Exception) {
            return [];
        }

        $permissions = [];

        foreach ($document->definitions as $definition) {
            if (!$definition instanceof OperationDefinitionNode) {
                continue;
            }

            $operationType = $definition->operation ?? 'query';

            foreach ($definition->selectionSet->selections as $selection) {
                if (!$selection instanceof FieldNode) {
                    continue;
                }

                $fieldName = $selection->name->value;

                // Skip introspection fields
                if (str_starts_with($fieldName, '__')) {
                    continue;
                }

                $resource = self::GRAPHQL_FIELD_MAP[$fieldName] ?? null;
                if ($resource === null) {
                    continue;
                }

                if ($operationType === 'query') {
                    $permissions[] = $resource . '/read';
                } else {
                    // Mutation — check if it's a 'create' operation
                    $isCreate = false;
                    foreach (self::CREATE_PREFIXES as $prefix) {
                        if (str_starts_with(strtolower($fieldName), $prefix)) {
                            $isCreate = true;
                            break;
                        }
                    }
                    $permissions[] = $resource . '/' . ($isCreate ? 'create' : 'write');
                }
            }
        }

        return array_unique($permissions);
    }
}
