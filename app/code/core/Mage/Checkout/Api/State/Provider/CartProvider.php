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

namespace Mage\Checkout\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage\Checkout\Api\Resource\Cart;
use Maho\ApiPlatform\Service\CartMapper;
use Maho\ApiPlatform\Service\CartService;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cart State Provider - Fetches cart data for API Platform
 *
 * @implements ProviderInterface<Cart>
 */
final class CartProvider implements ProviderInterface
{
    use AuthenticationTrait;

    private CartMapper $cartMapper;
    private CartService $cartService;

    public function __construct(Security $security)
    {
        $this->cartMapper = new CartMapper();
        $this->security = $security;
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
                /** @phpstan-ignore ternary.alwaysTrue */
                return $quote ? $this->cartMapper->mapQuoteToCart($quote) : null;
            }
            return null;
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
