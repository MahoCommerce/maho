<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cart State Provider - Fetches cart data for API Platform
 */
final class CartProvider extends \Maho\ApiPlatform\Provider
{
    private CartMapper $cartMapper;
    private CartService $cartService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartMapper = new CartMapper();
        $this->cartService = new CartService();
    }

    /**
     * Provide cart data based on operation type
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Cart
    {
        $operationName = $operation->getName();

        // Handle customerCart query - get current authenticated customer's cart
        if ($operationName === 'customerCart') {
            $customerId = $context['customer_id'] ?? $this->getAuthenticatedCustomerId();
            if ($customerId) {
                // Verify the authenticated user matches the requested customer
                $this->authorizeCustomerAccess((int) $customerId);
                $quote = $this->cartService->getCustomerCart((int) $customerId);
                return $this->cartMapper->mapQuoteToCart($quote);
            }
            return null;
        }

        // Handle guest-cart REST operations using masked ID from URI
        // Note: uriVariables[id] is cast to int by API Platform. Extract full masked ID from URI.
        if (in_array($operationName, ['get_guest_cart', 'get_guest_totals', 'get_guest_shipping', 'get_guest_payments'])) {
            $maskedId = null;
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                if (preg_match('#/guest-carts/([a-f0-9]{32})#i', $request->getPathInfo(), $m)) {
                    $maskedId = $m[1];
                }
            }
            $maskedId = $maskedId ?? (string) ($uriVariables['id'] ?? '');
            if (!$maskedId) {
                return null;
            }
            $quote = $this->cartService->getCart(null, $maskedId);
            if (!$quote) {
                return null;
            }
            $this->verifyCartAccess($quote, true);
            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // Handle getCartByMaskedId query
        if ($operationName === 'getCartByMaskedId') {
            $maskedId = $context['args']['maskedId'] ?? null;
            if (!$maskedId) {
                return null;
            }
            $quote = $this->cartService->getCart(null, $maskedId);
            if (!$quote) {
                return null;
            }
            $this->verifyCartAccess($quote, true);
            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // Handle standard cart query with cartId
        $cartId = $context['args']['cartId'] ?? $uriVariables['id'] ?? null;
        $maskedId = $context['args']['maskedId'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            return null;
        }

        // Verify cart ownership for authenticated customers
        $this->verifyCartAccess($quote, $maskedId !== null);

        return $this->cartMapper->mapQuoteToCart($quote);
    }

    /**
     * Verify the current user has access to the cart
     *
     * - Admins can access any cart
     * - Customers can only access their own cart
     * - Guest carts (no customer_id) are accessible via maskedId through public endpoints
     *
     * @throws AccessDeniedHttpException If access denied
     */
    private function verifyCartAccess(\Mage_Sales_Model_Quote $quote, bool $accessedByMaskedId = false): void
    {
        // Privileged users can access any cart
        if ($this->isAdmin() || $this->isPosUser() || $this->isApiUser()) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId();
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        // If cart belongs to a customer, verify ownership
        if ($cartCustomerId) {
            if ($authenticatedCustomerId === null || (int) $cartCustomerId !== $authenticatedCustomerId) {
                throw new AccessDeniedHttpException('You can only access your own cart');
            }
        } elseif (!$accessedByMaskedId) {
            // Guest carts accessed by numeric ID require admin role
            throw new AccessDeniedHttpException('Guest carts can only be accessed via masked ID');
        }
    }
}
