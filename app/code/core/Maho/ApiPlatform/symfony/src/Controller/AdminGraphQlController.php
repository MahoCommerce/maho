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

use Maho\ApiPlatform\Service\GraphQL\CartMutationHandler;
use Maho\ApiPlatform\Service\GraphQL\CustomerQueryHandler;
use Maho\ApiPlatform\Service\GraphQL\OrderMutationHandler;
use Maho\ApiPlatform\Service\GraphQL\ProductQueryHandler;
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
    public function __construct(
        private ProductQueryHandler $productHandler,
        private CartMutationHandler $cartHandler,
        private OrderMutationHandler $orderHandler,
        private CustomerQueryHandler $customerHandler,
    ) {}
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
            // Authentication handled by Symfony firewall (AdminSessionAuthenticator)
            // $_SERVER context vars are set by the authenticator
            $input = json_decode($content, true) ?? [];
            $storeId = (int) ($_SERVER['MAHO_STORE_ID'] ?? $input['variables']['storeId'] ?? 1);
            $adminUserId = (int) ($_SERVER['MAHO_ADMIN_USER_ID'] ?? 0);
            $customerId = $_SERVER['MAHO_POS_CUSTOMER_ID'] ?? null;


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

    /** Maximum nesting depth allowed in queries */
    private const MAX_QUERY_DEPTH = 10;

    /** Maximum query length in bytes */
    private const MAX_QUERY_LENGTH = 10000;

    private function executeQuery(string $query, ?array $variables, array $context): array
    {

        // Parse and execute
        $query = trim($query);

        // Query size limit
        if (strlen($query) > self::MAX_QUERY_LENGTH) {
            return ['errors' => [['message' => 'Query exceeds maximum allowed length']]];
        }

        // Depth limit — count max nesting of { }
        $depth = $this->calculateQueryDepth($query);
        if ($depth > self::MAX_QUERY_DEPTH) {
            return ['errors' => [['message' => 'Query exceeds maximum depth of ' . self::MAX_QUERY_DEPTH]]];
        }

        // Handle introspection
        if (str_contains($query, '__schema') || str_contains($query, '__type')) {
            return ['data' => $this->getIntrospectionSchema()];
        }

        // Parse operation name from the query
        if (preg_match('/(?:query|mutation)\s+(\w+)/', $query, $matches)) {
            $operation = $matches[1];
        } elseif (preg_match('/^\{\s*(\w+)/', $query, $matches)) {
            $operation = $matches[1];
        } else {
            return ['errors' => [['message' => 'Could not parse GraphQL operation name']]];
        }

        try {
            $data = $this->resolveOperation($operation, $variables ?? [], $context);
            return ['data' => $data];
        } catch (\Exception $e) {
            \Mage::logException($e);
            return ['errors' => [['message' => \Mage::getIsDeveloperMode() ? $e->getMessage() : 'An error occurred processing the request']]];
        }
    }

    /**
     * Calculate the maximum nesting depth of a GraphQL query
     */
    private function calculateQueryDepth(string $query): int
    {
        $maxDepth = 0;
        $currentDepth = 0;
        $inString = false;
        $escape = false;

        for ($i = 0, $len = strlen($query); $i < $len; $i++) {
            $char = $query[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $currentDepth++;
                $maxDepth = max($maxDepth, $currentDepth);
            } elseif ($char === '}') {
                $currentDepth--;
            }
        }

        return $maxDepth;
    }

    /**
     * Route operation to appropriate handler
     */
    private function resolveOperation(string $operation, array $variables, array $context): array
    {
        return match ($operation) {
            // Product operations (camelCase)
            'product', 'getProduct', 'GetProduct'
                => $this->productHandler->handleGetProduct($variables),
            'productBySku', 'getProductBySku', 'GetProductBySku'
                => $this->productHandler->handleGetProductBySku($variables),
            'productByBarcode', 'getProductByBarcode', 'GetProductByBarcode'
                => $this->productHandler->handleGetProductByBarcode($variables),
            'products', 'searchProducts', 'getProducts', 'GetProducts', 'SearchProducts'
                => $this->productHandler->handleSearchProducts($variables, $context),
            'getConfigurableProduct', 'GetConfigurableProduct'
                => $this->productHandler->handleGetConfigurableProduct($variables),

            // Cart operations (camelCase)
            'cart', 'getCart', 'GetCart'
                => $this->cartHandler->handleGetCart($variables),
            'createCart', 'createEmptyCart', 'CreateCart'
                => $this->cartHandler->handleCreateCart($variables, $context),
            'addToCart', 'addItemToCart', 'AddToCart'
                => $this->cartHandler->handleAddToCart($variables),
            'updateQty', 'updateCartItem', 'UpdateQty'
                => $this->cartHandler->handleUpdateQty($variables),
            'removeItem', 'removeItemFromCart', 'RemoveItem'
                => $this->cartHandler->handleRemoveItem($variables),
            'setItemFulfillment', 'setCartItemFulfillment', 'SetItemFulfillment'
                => $this->cartHandler->handleSetItemFulfillment($variables),
            'applyCoupon', 'applyCouponToCart', 'ApplyCoupon'
                => $this->cartHandler->handleApplyCoupon($variables),
            'removeCoupon', 'removeCouponFromCart', 'RemoveCoupon'
                => $this->cartHandler->handleRemoveCoupon($variables),
            'assignCustomerToCart', 'AssignCustomerToCart'
                => $this->cartHandler->handleAssignCustomer($variables),

            // Gift card operations (camelCase)
            'checkGiftCard', 'CheckGiftCard', 'checkGiftCardBalance', 'checkGiftcardBalance', 'CheckGiftCardBalance'
                => $this->cartHandler->handleCheckGiftCardBalance($variables),
            'applyGiftCard', 'applyGiftcardToCart', 'ApplyGiftCard'
                => $this->cartHandler->handleApplyGiftCard($variables),
            'removeGiftCard', 'removeGiftcardFromCart', 'RemoveGiftCard'
                => $this->cartHandler->handleRemoveGiftCard($variables),

            // Shipping operations (camelCase)
            'availableShippingMethods', 'getShippingMethods', 'GetShippingMethods'
                => $this->cartHandler->handleShippingMethods($variables),

            // Order operations (camelCase)
            'placeOrder', 'PlaceOrder'
                => $this->orderHandler->handlePlaceOrder($variables, $context),
            'placeOrderWithSplitPayments', 'PlaceOrderWithSplitPayments'
                => $this->orderHandler->handlePlaceOrderWithSplitPayments($variables, $context),
            'orderPayments', 'getOrderPayments', 'GetOrderPayments'
                => $this->orderHandler->handleOrderPayments($variables),
            'lookupOrder', 'getOrderByIncrementId', 'LookupOrder'
                => $this->orderHandler->handleLookupOrder($variables),
            'customerOrders', 'getCustomerOrders', 'CustomerOrders'
                => $this->orderHandler->handleGetCustomerOrders($variables),
            'recentOrders', 'getRecentOrders', 'RecentOrders'
                => $this->orderHandler->handleRecentOrders($variables),
            'searchOrders', 'SearchOrders'
                => $this->orderHandler->handleSearchOrders($variables),
            'processReturn', 'createCreditMemo', 'ProcessReturn'
                => $this->orderHandler->handleProcessReturn($variables, $context),

            // Customer operations (camelCase)
            'customers', 'searchCustomers', 'getCustomers', 'GetCustomers', 'SearchCustomers'
                => $this->customerHandler->handleSearchCustomers($variables),
            'customer', 'getCustomer', 'GetCustomer'
                => $this->customerHandler->handleGetCustomer($variables),
            'createCustomer', 'CreateCustomer'
                => $this->customerHandler->handleCreateCustomer($variables),
            'updateCustomerAddress', 'UpdateCustomerAddress', 'updateAddress', 'UpdateAddress'
                => $this->customerHandler->handleUpdateCustomerAddress($variables),

            // Category operations (camelCase)
            'categories', 'getCategories', 'GetCategories'
                => $this->customerHandler->handleGetCategories($variables, $context),

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
