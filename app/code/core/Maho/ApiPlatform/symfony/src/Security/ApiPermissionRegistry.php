<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Security;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
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
     * Format: 'resource' => ['label' => string, 'group' => string, 'operations' => ['op' => 'Label', ...]]
     */
    private const RESOURCES = [
        // ── Catalog ──
        'products'           => ['label' => 'Products', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'product-attributes' => ['label' => 'Product Attributes', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'product-images'     => ['label' => 'Product Images', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Upload & Update', 'delete' => 'Delete']],
        'product-links'      => ['label' => 'Product Links', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'product-options'    => ['label' => 'Custom Options', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Manage', 'delete' => 'Delete']],
        'tier-prices'        => ['label' => 'Tier Prices', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'categories'         => ['label' => 'Categories', 'group' => 'Storefront', 'section' => 'Catalog', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],

        // ── Orders & Fulfillment ──
        'orders'          => ['label' => 'Orders', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'create' => 'Place', 'write' => 'Manage']],
        'order-comments'  => ['label' => 'Order Comments', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'write' => 'Add']],
        'invoices'        => ['label' => 'Invoices', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'create' => 'Create']],
        'shipments'       => ['label' => 'Shipments', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'create' => 'Create']],
        'credit-memos'    => ['label' => 'Credit Memos', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'create' => 'Create']],
        'payments'        => ['label' => 'Payments', 'group' => 'Storefront', 'section' => 'Sales', 'operations' => ['read' => 'View', 'write' => 'Record']],

        // ── Customers ──
        'customers' => ['label' => 'Customers', 'group' => 'Storefront', 'section' => 'Customers', 'operations' => ['read' => 'View', 'create' => 'Register', 'write' => 'Update']],
        'addresses' => ['label' => 'Addresses', 'group' => 'Storefront', 'section' => 'Customers', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'carts'     => ['label' => 'Carts', 'group' => 'Storefront', 'section' => 'Customers', 'operations' => ['read' => 'View', 'write' => 'Create & Modify']],
        'wishlists' => ['label' => 'Wishlists', 'group' => 'Storefront', 'section' => 'Customers', 'operations' => ['read' => 'View', 'write' => 'Add/Remove']],
        'reviews'   => ['label' => 'Reviews', 'group' => 'Storefront', 'section' => 'Customers', 'operations' => ['read' => 'View', 'write' => 'Submit']],

        // ── Store & Config ──
        'stores'    => ['label' => 'Stores & Store Views', 'group' => 'Storefront', 'section' => 'System', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'countries' => ['label' => 'Countries', 'group' => 'Storefront', 'section' => 'System', 'operations' => ['read' => 'View']],

        // ── Other ──
        'giftcards'    => ['label' => 'Gift Cards', 'group' => 'Storefront', 'section' => 'Other', 'operations' => ['read' => 'Check Balance', 'create' => 'Create', 'write' => 'Adjust Balance']],
        'newsletter'   => ['label' => 'Newsletter', 'group' => 'Storefront', 'section' => 'Other', 'operations' => ['read' => 'View Status', 'write' => 'Subscribe/Unsubscribe']],

        'url-resolver' => ['label' => 'URL Resolver', 'group' => 'Storefront', 'section' => 'System', 'operations' => ['read' => 'Resolve']],
        'pos'          => ['label' => 'POS', 'group' => 'Storefront', 'section' => 'Other', 'operations' => ['read' => 'View', 'write' => 'Manage']],

        // ── Content ──
        'cms-pages'  => ['label' => 'CMS Pages', 'group' => 'Storefront', 'section' => 'Content', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'cms-blocks' => ['label' => 'CMS Blocks', 'group' => 'Storefront', 'section' => 'Content', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'blog-posts' => ['label' => 'Blog Posts', 'group' => 'Storefront', 'section' => 'Content', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'media'      => ['label' => 'Media', 'group' => 'Storefront', 'section' => 'Content', 'operations' => ['read' => 'List', 'write' => 'Upload', 'delete' => 'Delete']],
    ];

    /**
     * Resources with public read access (no auth required for GET).
     * These are listed in security.yaml as PUBLIC_ACCESS for GET.
     */
    private const PUBLIC_READ_RESOURCES = [
        'products', 'categories', 'cms-pages', 'cms-blocks', 'blog-posts', 'stores',
        'countries', 'url-resolver',
    ];

    /**
     * Resources that use customer JWT tokens (session-bound to logged-in customer).
     * These are accessible to authenticated customers, not API service accounts.
     */
    private const CUSTOMER_RESOURCES = [
        'carts'     => 'View cart, add/remove items, apply coupons, set shipping & payment',
        'addresses' => 'View, create, update, and delete saved addresses',
        'wishlists' => 'View wishlist, add/remove items, move to cart',
        'orders'    => 'View order history, place orders at checkout',
        'reviews'   => 'View and submit product reviews',
        'newsletter' => 'View subscription status, subscribe/unsubscribe',
    ];

    /**
     * URL path segment → resource name.
     * The resolver finds the last matching segment in the URL path.
     */
    private const SEGMENT_MAP = [
        'products'     => 'products',
        'categories'   => 'categories',
        'orders'       => 'orders',
        'customers'    => 'customers',
        'carts'        => 'carts',
        'guest-carts'  => 'carts',
        'addresses'    => 'addresses',
        'wishlists'    => 'wishlists',
        'wishlist'     => 'wishlists',
        'reviews'      => 'reviews',
        'giftcards'    => 'giftcards',
        'newsletter'   => 'newsletter',
        'cms-pages'    => 'cms-pages',
        'cms-blocks'   => 'cms-blocks',
        'blog-posts'   => 'blog-posts',
        'media'        => 'media',
        'stores'       => 'stores',
        'store-config' => 'stores',
        'countries'    => 'countries',
        'url-resolver' => 'url-resolver',
        'pos-payments' => 'pos',
        'shipments'    => 'shipments',
        'invoices'     => 'invoices',
        'items'        => null, // sub-resource, fall through to parent
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
        'cmsPage'            => 'cms-pages',
        'cmsPages'           => 'cms-pages',
        'cmsBlock'           => 'cms-blocks',
        'cmsBlocks'          => 'cms-blocks',
        'cmsBlockByIdentifier' => 'cms-blocks',
        // Blog
        'blogPost'           => 'blog-posts',
        'blogPosts'          => 'blog-posts',
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
     * @return array<string, array{label: string, group: string, operations: array<string, string>}>
     */
    public function getResources(): array
    {
        return self::RESOURCES;
    }

    /**
     * Get resource definitions grouped by their group key
     *
     * @return array<string, array<string, array{label: string, group: string, operations: array<string, string>}>>
     */
    public function getResourcesByGroup(): array
    {
        $grouped = [];
        foreach (self::RESOURCES as $resourceId => $config) {
            $group = $config['group'];
            $grouped[$group][$resourceId] = $config;
        }
        return $grouped;
    }

    /**
     * Get resources that have public read access (no auth required).
     *
     * @return array<string, string> resource ID => label
     */
    public function getPublicReadResources(): array
    {
        $result = [];
        foreach (self::PUBLIC_READ_RESOURCES as $resourceId) {
            /** @phpstan-ignore isset.offset */
            if (isset(self::RESOURCES[$resourceId])) {
                $result[$resourceId] = self::RESOURCES[$resourceId]['label'];
            }
        }
        return $result;
    }

    /**
     * Get resources that use customer JWT tokens (session-bound).
     *
     * @return array<string, string> resource ID => description
     */
    public function getCustomerResources(): array
    {
        return self::CUSTOMER_RESOURCES;
    }

    /**
     * Check if a resource has public read access
     */
    public function isPublicRead(string $resourceId): bool
    {
        return in_array($resourceId, self::PUBLIC_READ_RESOURCES, true);
    }

    /**
     * Get service-account permissions grouped for the admin role editor UI.
     *
     * Returns only resources/operations relevant to API service accounts:
     * - Skips read-only public resources (no permissions needed)
     * - Skips read operations on publicly-readable resources
     * - Groups as 'Storefront Operations' and 'Content Management'
     *
     * @return array<string, array<string, array<string, array{label: string, operations: array<string, string>}>>>
     */
    public function getServicePermissionsByGroup(): array
    {
        $groupLabels = [
            'Storefront' => 'Storefront Operations',
        ];

        /** @var array<string, array<string, array<string, array{label: string, operations: array<string, string>}>>> $grouped */
        $grouped = [];
        foreach (self::RESOURCES as $resourceId => $config) {
            $operations = $config['operations'];

            // Skip resources that are entirely public read-only
            /** @phpstan-ignore function.alreadyNarrowedType */
            if ($this->isPublicRead($resourceId) && count($operations) === 1 && array_key_exists('read', $operations)) {
                continue;
            }

            // For public-read resources, exclude the read operation from the tree
            if ($this->isPublicRead($resourceId)) {
                unset($operations['read']);
            }

            if (empty($operations)) {
                continue;
            }

            /** @phpstan-ignore nullCoalesce.offset */
            $groupKey = $groupLabels[$config['group']] ?? $config['group'];
            /** @phpstan-ignore nullCoalesce.offset */
            $section = $config['section'] ?? $groupKey;
            $grouped[$groupKey][$section][$resourceId] = [
                'label' => $config['label'],
                'operations' => $operations,
            ];
        }

        return $grouped;
    }

    /**
     * Resolve a REST URL path to a resource name.
     *
     * Splits the path into segments and returns the resource mapped by the
     * last known segment. This correctly handles nested paths like
     * /api/orders/5/shipments → 'shipments'.
     */
    public function resolveRestResource(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $resolved = null;

        foreach ($segments as $segment) {
            if (array_key_exists($segment, self::SEGMENT_MAP)) {
                $mapped = self::SEGMENT_MAP[$segment];
                if ($mapped !== null) {
                    $resolved = $mapped;
                }
                // null entries (like 'items') are skipped, keeping the parent
            }
        }

        return $resolved;
    }

    /**
     * Check if a resource defines a specific operation
     */
    public function resourceHasOperation(string $resource, string $operation): bool
    {
        return isset(self::RESOURCES[$resource]['operations'][$operation]);
    }

    /**
     * Parse a GraphQL query and return required resource/operation pairs.
     *
     * Handles FieldNode, FragmentSpreadNode, and InlineFragmentNode to
     * prevent permission bypass via fragment queries.
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

        // Build fragment map for resolving FragmentSpreadNode references
        $fragments = [];
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[$definition->name->value] = $definition;
            }
        }

        $permissions = [];

        foreach ($document->definitions as $definition) {
            if (!$definition instanceof OperationDefinitionNode) {
                continue;
            }

            $operationType = $definition->operation ?? 'query';
            $topLevelFields = $this->collectTopLevelFields($definition->selectionSet, $fragments);

            foreach ($topLevelFields as $fieldName) {
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

    /**
     * Collect top-level field names from a selection set, resolving fragments.
     *
     * @param array<string, FragmentDefinitionNode> $fragments
     * @return array<string>
     */
    private function collectTopLevelFields(SelectionSetNode $selectionSet, array $fragments): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fields[] = $selection->name->value;
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragmentName = $selection->name->value;
                if (isset($fragments[$fragmentName])) {
                    $fields = array_merge(
                        $fields,
                        $this->collectTopLevelFields($fragments[$fragmentName]->selectionSet, $fragments),
                    );
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $fields = array_merge(
                    $fields,
                    $this->collectTopLevelFields($selection->selectionSet, $fragments),
                );
            }
        }

        return $fields;
    }
}
