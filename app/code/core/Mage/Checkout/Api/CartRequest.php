<?php

/**
 * Finds the cart that an API request names, from its path, its body or its GraphQL arguments, and the store of a new cart.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CartRequest
{
    /**
     * The masked cart id a /guest-carts/{id}/… path names, or null when the path
     * has no such segment. The whole segment must match: on a partial match one
     * malformed id would resolve to a cart the caller never wrote.
     */
    public static function maskedIdFromPath(string $path): ?string
    {
        if (!preg_match('#/guest-carts/([^/?]+)#', $path, $m)) {
            return null;
        }
        return \Mage::getService('checkout/cart')->isValidMaskedId($m[1]) ? $m[1] : null;
    }

    /**
     * Resolve a cart from API request context.
     * Handles both /carts/{id} (numeric) and /guest-carts/{maskedId} (hex) patterns.
     *
     * @return array{quote: \Mage_Sales_Model_Quote|null, accessedByMaskedId: bool, maskedId: string|null}
     */
    public static function resolve(
        array $uriVariables,
        array $context,
    ): array {
        $request = $context['request'] ?? null;
        $args = $context['args']['input'] ?? $context['args'] ?? [];

        // Bridge REST request body for Provider context (Processor does this later, but Provider runs first)
        if (empty($args) && $request instanceof \Symfony\Component\HttpFoundation\Request) {
            try {
                $body = \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]');
            } catch (\JsonException) {
                throw new BadRequestHttpException('Invalid JSON in request body');
            }
            if (is_array($body)) {
                $args = $body;
            }
        }

        // On a guest-carts route the path segment is the identifier, so it wins
        // over a body maskedId: the caller and the lookup must never disagree
        // about which cart a request names. A body maskedId applies only where
        // there is no such segment (GraphQL, /carts). The path is read from the
        // raw string because API Platform casts URI placeholders to Cart.id (int),
        // which truncates a hex masked id.
        $maskedId = null;
        $isGuestCartRoute = false;
        if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
            $isGuestCartRoute = str_contains($request->getPathInfo(), '/guest-carts/');
            $maskedId = self::maskedIdFromPath($request->getPathInfo());
        }
        if (!$maskedId && !$isGuestCartRoute && is_string($args['maskedId'] ?? null)) {
            $maskedId = $args['maskedId'];
        }

        // Last: cartId from GraphQL args or uriVariables. On the guest-carts
        // route the {id} is a masked id, never a numeric quote id. If it wasn't a
        // valid masked id above, the cart simply doesn't exist. Falling back to
        // numeric loading there, from the body or from the URI, would resolve an
        // unrelated quote and leak its existence (404 vs 401) to an enumerating
        // caller.
        $cartId = null;
        if (!$isGuestCartRoute) {
            $cartId = isset($args['cartId']) ? (int) $args['cartId'] : null;
            if (!$cartId && !$maskedId && isset($uriVariables['id'])) {
                $cartId = (int) $uriVariables['id'];
            }
        }

        if (!$maskedId && !$cartId) {
            return ['quote' => null, 'accessedByMaskedId' => false, 'maskedId' => null];
        }

        $quote = self::load($cartId, $maskedId);
        return ['quote' => $quote, 'accessedByMaskedId' => $maskedId !== null, 'maskedId' => $maskedId];
    }

    /**
     * Load a cart by its masked id or, when there is none, by its id. Give it the display currency
     * that the request asks for with X-Currency-Code.
     */
    public static function load(?int $cartId, ?string $maskedId = null): ?\Mage_Sales_Model_Quote
    {
        $service = \Mage::getService('checkout/cart');
        if ($maskedId) {
            $quote = $service->getByMaskedId($maskedId);
        } else {
            $quote = $cartId ? $service->getById($cartId) : null;
        }

        if ($quote) {
            StoreContext::applyRequestedCurrencyToQuote($quote);
        }

        return $quote;
    }

    /**
     * The store of a new cart: $requestedStoreId when the request names one, else the store of the API
     * request. A cart never belongs to the admin store (0).
     */
    public static function storeId(?int $requestedStoreId = null): int
    {
        return $requestedStoreId ?: (StoreContext::getStoreId() ?: StoreContext::getDefaultStoreId());
    }
}
