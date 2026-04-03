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

        // customerCart query — get authenticated user's active cart
        if ($operationName === 'customerCart') {
            $customerId = $context['customer_id'] ?? $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                return null;
            }
            $this->authorizeCustomerAccess((int) $customerId);
            $quote = $this->cartService->getCustomerCart((int) $customerId);
            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // All other operations: resolve cart via unified method
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked] =
            $this->cartService->resolveCartFromRequest($uriVariables, $context);

        if (!$quote) {
            return null;
        }

        $this->cartService->verifyCartAccess(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->isAdmin() || $this->isPosUser() || $this->isApiUser(),
        );

        return $this->cartMapper->mapQuoteToCart($quote);
    }
}
