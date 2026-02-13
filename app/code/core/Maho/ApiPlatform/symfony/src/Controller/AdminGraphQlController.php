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

namespace Maho\ApiPlatform\Controller;

use Maho\ApiPlatform\Service\CartService;
use Maho\ApiPlatform\Service\CustomerService;
use Maho\ApiPlatform\Service\GraphQL\CartMutationHandler;
use Maho\ApiPlatform\Service\GraphQL\CustomerQueryHandler;
use Maho\ApiPlatform\Service\GraphQL\OrderMutationHandler;
use Maho\ApiPlatform\Service\GraphQL\ProductQueryHandler;
use Maho\ApiPlatform\Service\OrderService;
use Maho\ApiPlatform\Service\PaymentService;
use Maho\ApiPlatform\Service\ProductService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin GraphQL Controller
 *
 * Handles GraphQL requests for admin users (POS, admin tools).
 * Uses handler-based resolution for clean separation of concerns.
 */
#[Route('/api/admin/graphql', name: 'api_admin_graphql', methods: ['GET', 'POST'])]
class AdminGraphQlController
{
    public function __invoke(Request $request): Response
    {
        // GET requests return info about the endpoint
        if ($request->isMethod('GET')) {
            return new JsonResponse([
                'message' => 'Admin GraphQL endpoint. Send POST requests with your query.',
                'methods' => ['POST'],
            ]);
        }

        // Parse request
        $content = $request->getContent();
        $input = json_decode($content, true) ?? [];

        $query = $input['query'] ?? '';
        $variables = $input['variables'] ?? null;

        if (empty($query)) {
            return new JsonResponse([
                'errors' => [['message' => 'No GraphQL query provided']],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Get context from server vars (set by Maho controller) or request
            $input = json_decode($content, true) ?? [];
            $storeId = (int) ($_SERVER['MAHO_STORE_ID'] ?? $input['variables']['storeId'] ?? 1);
            $adminUserId = $_SERVER['MAHO_ADMIN_USER_ID'] ?? null;
            $customerId = $_SERVER['MAHO_POS_CUSTOMER_ID'] ?? null;

            // For POS - validate form_key using Maho's standard method
            // The form_key is tied to the admin session and must be validated properly
            if ($adminUserId === null && !empty($input['form_key'])) {
                $session = \Mage::getSingleton('core/session');
                if ($session->validateFormKey($input['form_key'])) {
                    // Valid form_key from admin session - get admin user from session
                    $adminSession = \Mage::getSingleton('admin/session');
                    if ($adminSession->isLoggedIn()) {
                        $adminUserId = (int) $adminSession->getUser()->getId();
                    }
                }
            }

            if ($adminUserId === null) {
                return new JsonResponse([
                    'errors' => [['message' => 'Admin authentication required']],
                ], Response::HTTP_UNAUTHORIZED);
            }

            $context = [
                'store_id' => $storeId,
                'is_admin' => true,
                'admin_user_id' => $adminUserId,
                'customer_id' => $customerId,
            ];

            $result = $this->executeQuery($query, $variables, $context);

            // Admin API always uses camelCase (GraphQL standard)
            // The naming convention setting only applies to storefront API
            return new JsonResponse($result);

        } catch (\Throwable $e) {
            \Mage::logException($e);

            return new JsonResponse([
                'errors' => [[
                    'message' => \Mage::getIsDeveloperMode() ? $e->getMessage() : 'Internal server error',
                    'extensions' => [
                        'code' => 'INTERNAL_SERVER_ERROR',
                    ],
                ]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function executeQuery(string $query, ?array $variables, array $context): array
    {
        // Initialize handlers
        $handlers = $this->initializeHandlers($context['store_id']);

        // Parse and execute
        $query = trim($query);

        // Handle introspection
        if (str_contains($query, '__schema') || str_contains($query, '__type')) {
            return ['data' => $this->getIntrospectionSchema()];
        }

        // Parse operation name
        if (preg_match('/(?:query|mutation)\s+(\w+)/', $query, $matches)) {
            $operation = $matches[1];
        } elseif (preg_match('/\{\s*(\w+)/', $query, $matches)) {
            $operation = $matches[1];
        } else {
            return ['errors' => [['message' => 'Could not parse GraphQL query']]];
        }

        try {
            $data = $this->resolveOperation($operation, $variables ?? [], $context, $handlers);
            return ['data' => $data];
        } catch (\Exception $e) {
            \Mage::logException($e);
            return ['errors' => [['message' => \Mage::getIsDeveloperMode() ? $e->getMessage() : 'An error occurred processing the request']]];
        }
    }

    /**
     * Initialize domain handlers with their dependencies
     *
     * @return array<string, ProductQueryHandler|CartMutationHandler|OrderMutationHandler|CustomerQueryHandler>
     */
    private function initializeHandlers(int $storeId): array
    {
        // Get Meilisearch client if available
        $meilisearchClient = null;
        $indexBaseName = null;
        if (\Mage::helper('core')->isModuleEnabled('Meilisearch_Search')) {
            try {
                /** @phpstan-ignore-next-line */
                $meilisearchHelper = \Mage::helper('meilisearch_search/meilisearchhelper');
                /** @phpstan-ignore-next-line */
                $meilisearchClient = $meilisearchHelper->getClient();
                /** @phpstan-ignore-next-line */
                $productHelper = \Mage::helper('meilisearch_search/entity_producthelper');
                /** @phpstan-ignore-next-line */
                $indexBaseName = $productHelper->getBaseIndexName($storeId);
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        // Create services
        $productService = new ProductService($meilisearchClient, $indexBaseName);
        $cartService = new CartService();
        $customerService = new CustomerService();
        $orderService = new OrderService();

        // Create handlers
        return [
            'product' => new ProductQueryHandler($productService),
            'cart' => new CartMutationHandler($cartService),
            'order' => new OrderMutationHandler($orderService),
            'customer' => new CustomerQueryHandler($customerService),
        ];
    }

    /**
     * Route operation to appropriate handler
     *
     * @param array<string, ProductQueryHandler|CartMutationHandler|OrderMutationHandler|CustomerQueryHandler> $handlers
     */
    private function resolveOperation(string $operation, array $variables, array $context, array $handlers): array
    {
        $productHandler = $handlers['product'];
        $cartHandler = $handlers['cart'];
        $orderHandler = $handlers['order'];
        $customerHandler = $handlers['customer'];

        return match ($operation) {
            // Product operations (camelCase)
            'product', 'getProduct', 'GetProduct'
                => $productHandler->handleGetProduct($variables),
            'productBySku', 'getProductBySku', 'GetProductBySku'
                => $productHandler->handleGetProductBySku($variables),
            'productByBarcode', 'getProductByBarcode', 'GetProductByBarcode'
                => $productHandler->handleGetProductByBarcode($variables),
            'products', 'searchProducts', 'getProducts', 'GetProducts', 'SearchProducts'
                => $productHandler->handleSearchProducts($variables, $context),
            'getConfigurableProduct', 'GetConfigurableProduct'
                => $productHandler->handleGetConfigurableProduct($variables),

            // Cart operations (camelCase)
            'cart', 'getCart', 'GetCart'
                => $cartHandler->handleGetCart($variables),
            'createCart', 'createEmptyCart', 'CreateCart'
                => $cartHandler->handleCreateCart($variables, $context),
            'addToCart', 'addItemToCart', 'AddToCart'
                => $cartHandler->handleAddToCart($variables),
            'updateQty', 'updateCartItem', 'UpdateQty'
                => $cartHandler->handleUpdateQty($variables),
            'removeItem', 'removeItemFromCart', 'RemoveItem'
                => $cartHandler->handleRemoveItem($variables),
            'setItemFulfillment', 'setCartItemFulfillment', 'SetItemFulfillment'
                => $cartHandler->handleSetItemFulfillment($variables),
            'applyCoupon', 'applyCouponToCart', 'ApplyCoupon'
                => $cartHandler->handleApplyCoupon($variables),
            'removeCoupon', 'removeCouponFromCart', 'RemoveCoupon'
                => $cartHandler->handleRemoveCoupon($variables),
            'assignCustomerToCart', 'AssignCustomerToCart'
                => $cartHandler->handleAssignCustomer($variables),

            // Gift card operations (camelCase)
            'checkGiftCard', 'CheckGiftCard', 'checkGiftCardBalance', 'checkGiftcardBalance', 'CheckGiftCardBalance'
                => $cartHandler->handleCheckGiftCardBalance($variables),
            'applyGiftCard', 'applyGiftcardToCart', 'ApplyGiftCard'
                => $cartHandler->handleApplyGiftCard($variables),
            'removeGiftCard', 'removeGiftcardFromCart', 'RemoveGiftCard'
                => $cartHandler->handleRemoveGiftCard($variables),

            // Shipping operations (camelCase)
            'availableShippingMethods', 'getShippingMethods', 'GetShippingMethods'
                => $cartHandler->handleShippingMethods($variables),

            // Order operations (camelCase)
            'placeOrder', 'PlaceOrder'
                => $orderHandler->handlePlaceOrder($variables, $context),
            'placeOrderWithSplitPayments', 'PlaceOrderWithSplitPayments'
                => $orderHandler->handlePlaceOrderWithSplitPayments($variables, $context),
            'orderPayments', 'getOrderPayments', 'GetOrderPayments'
                => $orderHandler->handleOrderPayments($variables),
            'lookupOrder', 'getOrderByIncrementId', 'LookupOrder'
                => $orderHandler->handleLookupOrder($variables),
            'customerOrders', 'getCustomerOrders', 'CustomerOrders'
                => $orderHandler->handleGetCustomerOrders($variables),
            'recentOrders', 'getRecentOrders', 'RecentOrders'
                => $orderHandler->handleRecentOrders($variables),
            'searchOrders', 'SearchOrders'
                => $orderHandler->handleSearchOrders($variables),
            'processReturn', 'createCreditMemo', 'ProcessReturn'
                => $orderHandler->handleProcessReturn($variables, $context),

            // Customer operations (camelCase)
            'customers', 'searchCustomers', 'getCustomers', 'GetCustomers', 'SearchCustomers'
                => $customerHandler->handleSearchCustomers($variables),
            'customer', 'getCustomer', 'GetCustomer'
                => $customerHandler->handleGetCustomer($variables),
            'createCustomer', 'CreateCustomer'
                => $customerHandler->handleCreateCustomer($variables),
            'updateCustomerAddress', 'UpdateCustomerAddress', 'updateAddress', 'UpdateAddress'
                => $customerHandler->handleUpdateCustomerAddress($variables),

            // Category operations (camelCase)
            'categories', 'getCategories', 'GetCategories'
                => $customerHandler->handleGetCategories($variables, $context),

            default => throw new BadRequestHttpException("Unknown operation: {$operation}"),
        };
    }

    /**
     * Return minimal introspection schema
     */
    private function getIntrospectionSchema(): array
    {
        return [
            '__schema' => [
                'queryType' => ['name' => 'Query'],
                'mutationType' => ['name' => 'Mutation'],
                'types' => [],
            ],
        ];
    }
}
