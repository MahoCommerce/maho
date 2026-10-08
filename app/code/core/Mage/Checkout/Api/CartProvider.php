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
 * all require unified resolution via CartRequest and the cart service.
 */
final class CartProvider extends \Maho\ApiPlatform\Provider
{
    private CartMapper $cartMapper;
    private \Mage_Checkout_Service_Cart $cartService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartMapper = new CartMapper();
        $this->cartService = \Mage::getService('checkout/cart');
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
            $quote = $this->cartService->getForCustomer((int) $customerId, StoreContext::getStoreId());
            return $this->cartMapper->mapQuoteToCart($quote);
        }

        // All other operations: resolve cart via unified method
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked]
            = CartRequest::resolve($uriVariables, $context);

        if (!$quote) {
            return null;
        }

        // hasBackendAccess: admin, or a service token holding a carts grant. A bare
        // api_user token without the grant must NOT bypass cart ownership
        // (mirrors the write side's isPrivilegedCartActor()).
        $this->cartService->verifyAccess(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->hasBackendAccess('carts'),
        );

        // Guest sub-resource endpoints return a bare, focused JSON shape (not the
        // full Cart), matching the documented frontend contract: /totals is the
        // flat totals object and /payment-methods a plain list of methods. The
        // authenticated /carts/{id}/* variants deliberately return the full Cart.
        // They bypass the mapper's read-boundary collection, so collect here.
        if ($operationName === 'get_guest_totals') {
            if (!$quote->getTotalsCollectedFlag()) {
                $this->cartService->collectAndVerifyTotals($quote);
            }
            return $this->respondRaw($this->cartMapper->mapPricesToArray($quote));
        }

        if ($operationName === 'get_guest_payments') {
            if (!$quote->getTotalsCollectedFlag()) {
                $this->cartService->collectAndVerifyTotals($quote);
            }
            // payment_method_is_active observers must see the cart's own store, not the caller's
            return $this->respondRaw($this->cartService->inQuoteStoreScope(
                $quote,
                fn(): array => $this->cartMapper->getAvailablePaymentMethods($quote),
            ));
        }

        return $this->cartMapper->mapQuoteToCart($quote);
    }
}
