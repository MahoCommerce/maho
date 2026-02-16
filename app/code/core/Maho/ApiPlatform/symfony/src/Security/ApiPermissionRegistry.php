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
        // Storefront resources
        'products'     => ['label' => 'Products', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'categories'   => ['label' => 'Categories', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'orders'       => ['label' => 'Orders', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'create' => 'Place', 'write' => 'Manage']],
        'customers'    => ['label' => 'Customers', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'create' => 'Register', 'write' => 'Update']],
        'carts'        => ['label' => 'Carts', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Create & Modify']],
        'addresses'    => ['label' => 'Addresses', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Manage']],
        'wishlists'    => ['label' => 'Wishlists', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Add/Remove']],
        'reviews'      => ['label' => 'Reviews', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Submit']],
        'shipments'    => ['label' => 'Shipments', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'create' => 'Create']],
        'giftcards'    => ['label' => 'Gift Cards', 'group' => 'Storefront', 'operations' => ['read' => 'Check Balance', 'create' => 'Create', 'write' => 'Adjust Balance']],
        'newsletter'   => ['label' => 'Newsletter', 'group' => 'Storefront', 'operations' => ['read' => 'View Status', 'write' => 'Subscribe/Unsubscribe']],
        'cms'          => ['label' => 'CMS Pages & Blocks', 'group' => 'Storefront', 'operations' => ['read' => 'View']],
        'blog'         => ['label' => 'Blog Posts', 'group' => 'Storefront', 'operations' => ['read' => 'View']],
        'stores'       => ['label' => 'Store Config', 'group' => 'Storefront', 'operations' => ['read' => 'View']],
        'countries'    => ['label' => 'Countries', 'group' => 'Storefront', 'operations' => ['read' => 'View']],
        'url-resolver' => ['label' => 'URL Resolver', 'group' => 'Storefront', 'operations' => ['read' => 'Resolve']],
        'invoices'     => ['label' => 'Invoices', 'group' => 'Storefront', 'operations' => ['read' => 'View']],
        'pos'          => ['label' => 'POS', 'group' => 'Storefront', 'operations' => ['read' => 'View', 'write' => 'Manage']],

        // Admin content resources
        'admin/cms-pages'  => ['label' => 'CMS Pages', 'group' => 'Admin', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'admin/cms-blocks' => ['label' => 'CMS Blocks', 'group' => 'Admin', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'admin/blog-posts' => ['label' => 'Blog Posts', 'group' => 'Admin', 'operations' => ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete']],
        'admin/media'      => ['label' => 'Media', 'group' => 'Admin', 'operations' => ['read' => 'List', 'write' => 'Upload', 'delete' => 'Delete']],
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
        'cms-pages'    => 'admin/cms-pages',
        'cms-blocks'   => 'admin/cms-blocks',
        'blog-posts'   => 'admin/blog-posts',
        'media'        => 'admin/media',
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
        'cmsPage'            => 'admin/cms-pages',
        'cmsPages'           => 'admin/cms-pages',
        'cmsBlock'           => 'admin/cms-blocks',
        'cmsBlocks'          => 'admin/cms-blocks',
        'cmsBlockByIdentifier' => 'admin/cms-blocks',
        // Blog
        'blogPost'           => 'admin/blog-posts',
        'blogPosts'          => 'admin/blog-posts',
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
