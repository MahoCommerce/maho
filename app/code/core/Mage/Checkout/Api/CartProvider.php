<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cart State Provider - Fetches cart data for API Platform.
 *
 * Overrides provide() because Cart has non-standard routing:
 * guest carts (masked ID), customer carts, and numeric ID carts
 * all require unified resolution via CartService.
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
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Cart|Response|null
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // `customer` query (field `customerCart`): the authenticated user's active cart
        if ($operationName === 'customer') {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                return null;
            }
            $this->assertCustomerAccess((int) $customerId);
            $quote = $this->cartService->getCustomerCart((int) $customerId);
            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // All other operations: resolve cart via unified method. For write
        // operations the processor loads its own quote instance afterwards, so
        // totals collected here would be thrown away; skip them.
        $isWriteOperation = ($operation instanceof \ApiPlatform\Metadata\HttpOperation
                && !in_array($operation->getMethod(), ['GET', 'HEAD'], true))
            || $operation instanceof \ApiPlatform\Metadata\GraphQl\Mutation;
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked] =
            $this->cartService->resolveCartFromRequest($uriVariables, $context, !$isWriteOperation);

        if (!$quote) {
            return null;
        }

        // hasBackendAccess: admin, or a service token holding a carts grant. A bare
        // api_user token without the grant must NOT bypass cart ownership
        // (mirrors the write side's isPrivilegedCartActor()).
        $this->cartService->verifyCartAccess(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->hasBackendAccess('carts'),
        );

        // Guest sub-resource endpoints return a bare, focused JSON shape (not the
        // full Cart), matching the documented frontend contract: /totals is the
        // flat totals object and /payment-methods a plain list of methods. The
        // authenticated /carts/{id}/* variants deliberately return the full Cart.
        if ($operationName === 'get_guest_totals') {
            return $this->respondRaw($this->cartMapper->mapPricesToArray($quote));
        }

        if ($operationName === 'get_guest_payments') {
            return $this->respondRaw($this->cartMapper->getAvailablePaymentMethods($quote));
        }

        return $this->cartMapper->mapQuoteToCart($quote);
    }
}
