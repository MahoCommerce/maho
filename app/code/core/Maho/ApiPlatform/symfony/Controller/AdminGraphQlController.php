<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Controller;

use Mage\Checkout\Api\GraphQL\CartMutationHandler;
use Mage\Customer\Api\GraphQL\CustomerQueryHandler;
use Mage\Sales\Api\GraphQL\OrderMutationHandler;
use Mage\Catalog\Api\GraphQL\ProductQueryHandler;
use Maho\ApiPlatform\Security\SameOriginGuard;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Admin GraphQL Controller.
 *
 * Handles GraphQL requests for authenticated admin users.
 * Uses handler-based resolution for clean separation of concerns.
 *
 * Deliberately a separate authorization surface from REST and frontend
 * GraphQL. Dispatch is a hand-rolled match table rather than API Platform
 * operations, so nothing here evaluates an operation `security:` expression,
 * and the handlers hand-serialize with `$dto->toArray()` rather than going
 * through the serializer, so per-property `#[ApiProperty(security: …)]` gates
 * do not apply either. What gates it instead: the firewall admits only
 * fully-authenticated admin sessions, and every dispatched handler method calls
 * `AdminAcl::checkResource()` against the same ADMIN_RESOURCE constant
 * AdminAclListener enforces on the REST surface, which is what makes
 * backend field visibility correct here (every caller holds the resource's
 * admin ACL). AdminGraphQlAclCoverageTest keeps that per-handler check honest.
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

        // This endpoint authenticates via the admin session cookie, so it needs
        // CSRF protection: reject any state-changing request whose Origin/Referer
        // isn't the admin's own host (a cross-site form POST can't forge it).
        SameOriginGuard::assert($request);

        // Parse request
        $body = RequestInput::tryDecode($request);
        if ($body === null) {
            return new JsonResponse([
                'errors' => [['message' => 'Invalid JSON in request body']],
            ], Response::HTTP_BAD_REQUEST);
        }
        $input = RequestInput::of($body);

        try {
            $query = $input->string('query') ?? '';
            $variables = $input->array('variables');
            $operationName = $input->string('operationName');
            // A storeId that is not an integer is left to the store the admin bridge chose
            $requestedStoreId = RequestInput::toInt($variables['storeId'] ?? null);
        } catch (NotNormalizableValueException $e) {
            return new JsonResponse([
                'errors' => [['message' => $e->getMessage()]],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($query)) {
            return new JsonResponse([
                'errors' => [['message' => 'No GraphQL query provided']],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Authentication handled by Symfony firewall (AdminSessionAuthenticator)
            // $_SERVER context vars are set by the authenticator
            $storeId = (int) ($_SERVER['MAHO_STORE_ID'] ?? $requestedStoreId ?? 1);
            $adminUserId = (int) ($_SERVER['MAHO_ADMIN_USER_ID'] ?? 0);

            $context = [
                'store_id' => $storeId,
                'is_admin' => true,
                'admin_user_id' => $adminUserId,
            ];

            $result = $this->executeQuery($query, $variables, $context, $operationName);

            // Admin API always uses camelCase (GraphQL standard)
            // The naming convention setting only applies to frontend API
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

    private function executeQuery(string $query, ?array $variables, array $context, ?string $operationName = null): array
    {
        // Parse and execute
        $query = trim($query);

        // Query size limit
        if (strlen($query) > self::MAX_QUERY_LENGTH) {
            return ['errors' => [['message' => 'Query exceeds maximum allowed length']]];
        }

        // Depth limit, count max nesting of { }
        $depth = $this->calculateQueryDepth($query);
        if ($depth > self::MAX_QUERY_DEPTH) {
            return ['errors' => [['message' => 'Query exceeds maximum depth of ' . self::MAX_QUERY_DEPTH]]];
        }

        // Introspection is intentionally unimplemented on the admin GraphQL
        // endpoint: this controller hand-rolls operation dispatch via a match
        // table rather than backing onto a real schema, so any introspection
        // response would misrepresent the API surface. Return an explicit error
        // so tooling fails loudly instead of silently treating the schema as
        // empty.
        if (str_contains($query, '__schema') || str_contains($query, '__type')) {
            return ['errors' => [[
                'message' => 'Schema introspection is not supported on the admin GraphQL endpoint',
                'extensions' => ['code' => 'INTROSPECTION_DISABLED'],
            ]]];
        }

        // Use operationName from request if available (standard GraphQL protocol),
        // fall back to regex parsing for anonymous single-operation queries
        $operation = $operationName;
        if (!$operation) {
            if (preg_match('/(?:query|mutation)\s+(\w+)/', $query, $matches)) {
                $operation = $matches[1];
            } elseif (preg_match('/^\{\s*(\w+)/', $query, $matches)) {
                $operation = $matches[1];
            } else {
                return ['errors' => [['message' => 'Could not parse GraphQL operation name']]];
            }
        }

        try {
            $data = $this->resolveOperation($operation, $variables ?? [], $context);
            return ['data' => $data];
        } catch (NotNormalizableValueException $e) {
            return ['errors' => [['message' => $e->getMessage(), 'extensions' => ['code' => 'BAD_USER_INPUT']]]];
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
     * Route operation to appropriate handler.
     *
     * Per-operation Maho admin ACL is enforced inline by each handler method
     * via Maho\ApiPlatform\Security\AdminAcl::checkResource(...). Each
     * handler imports its own resource class, no central
     * operation→resource map, so 3rd-party modules can add operations
     * without touching this controller's metadata.
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
                => $this->orderHandler->handlePlaceOrder($variables),
            'lookupOrder', 'getOrderByIncrementId', 'LookupOrder'
                => $this->orderHandler->handleLookupOrder($variables),
            'customerOrders', 'getCustomerOrders', 'CustomerOrders'
                => $this->orderHandler->handleGetCustomerOrders($variables),
            'recentOrders', 'getRecentOrders', 'RecentOrders'
                => $this->orderHandler->handleRecentOrders($variables),
            'searchOrders', 'SearchOrders'
                => $this->orderHandler->handleSearchOrders($variables),
            'createCreditMemo', 'CreateCreditMemo'
                => $this->orderHandler->handleCreateCreditMemo($variables, $context),

            // Customer operations (camelCase)
            'customers', 'searchCustomers', 'getCustomers', 'GetCustomers', 'SearchCustomers'
                => $this->customerHandler->handleSearchCustomers($variables),
            'customer', 'getCustomer', 'GetCustomer'
                => $this->customerHandler->handleGetCustomer($variables),
            'createCustomer', 'CreateCustomer'
                => $this->customerHandler->handleCreateCustomer($variables, $context),
            'updateCustomerAddress', 'UpdateCustomerAddress', 'updateAddress', 'UpdateAddress'
                => $this->customerHandler->handleUpdateCustomerAddress($variables),

            // Category operations (camelCase)
            'categories', 'getCategories', 'GetCategories'
                => $this->productHandler->handleGetCategories($variables, $context),

            default => throw new BadRequestHttpException("Unknown operation: {$operation}"),
        };
    }

}
