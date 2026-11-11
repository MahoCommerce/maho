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

use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Service\StoreContext;

final class CartRequest
{
    /** The field of the quote address => the field of an address in the body, as mapAddress() reads it. */
    public const ADDRESS_FIELD_NAMES = [
        'prefix' => 'prefix',
        'firstname' => 'firstName',
        'middlename' => 'middleName',
        'lastname' => 'lastName',
        'suffix' => 'suffix',
        'company' => 'company',
        'street' => 'street',
        'city' => 'city',
        'region' => 'region',
        'region_id' => 'regionId',
        'postcode' => 'postcode',
        'country_id' => 'countryId',
        'telephone' => 'telephone',
        'vat_id' => 'vatId',
        'save_in_address_book' => 'saveInAddressBook',
    ];

    /**
     * Return the actor of a payment selection or of an address of an order. Staff that calls in the admin scope
     * enters an order of the back office, with the payment methods for internal use. Staff that calls for a store
     * view sells with the address form and the payment methods of the checkout of that store.
     * $isPrivileged says that the caller is staff: an admin, or a token with the grant to write carts or orders.
     */
    public static function backOfficeActor(bool $isPrivileged): \Mage_Checkout_Model_Actor
    {
        return $isPrivileged && \Mage::app()->getStore()->isAdmin()
            ? \Mage_Checkout_Model_Actor::Staff
            : \Mage_Checkout_Model_Actor::Customer;
    }

    /**
     * Map an address of the body (camelCase) to the fields of the quote address. Each address write of the API
     * uses it, so that all of them find the region in the same way: a client often sends only the name or the code
     * of the region, and a country with a list of regions needs the region ID. The code is the more exact name, so
     * the lookup tries it first, then the name. When a region matches, the text becomes its name.
     *
     * @param array<string, mixed> $input
     * @param string $path where $input sits in the request, for its errors
     * @return array<string, mixed>
     */
    public static function mapAddress(array $input, string $path = ''): array
    {
        $fields = RequestInput::of($input, $path);
        $regionId = ($input['regionId'] ?? '') === '' ? null : $fields->int('regionId');
        $regionText = $fields->string('region');
        $countryId = $fields->string('countryId') ?? '';

        if (!$regionId && $regionText && $countryId !== '') {
            $region = \Mage::getModel('directory/region')->loadByCodeOrName($regionText, $countryId);
            if ($region->getId()) {
                $regionId = (int) $region->getId();
                $regionText = $region->getName();
            }
        }

        $street = is_string($input['street'] ?? null) ? [$input['street']] : ($fields->stringList('street') ?? []);

        return [
            'prefix' => $fields->string('prefix'),
            'firstname' => $fields->string('firstName') ?? '',
            'middlename' => $fields->string('middleName'),
            'lastname' => $fields->string('lastName') ?? '',
            'suffix' => $fields->string('suffix'),
            'company' => $fields->string('company'),
            'street' => $street,
            'city' => $fields->string('city') ?? '',
            'region' => $regionText,
            'region_id' => $regionId,
            'postcode' => $fields->string('postcode') ?? '',
            'country_id' => $countryId,
            'telephone' => $fields->string('telephone') ?? '',
            'vat_id' => $fields->string('vatId'),
            'save_in_address_book' => $fields->bool('saveInAddressBook') ?? false,
        ];
    }

    /**
     * Give each address field of $e the name that the client sent. $prefixes is the prefix of the service =>
     * the name of the address in the body, for a body that holds several addresses.
     *
     * @param array<string, string> $prefixes
     */
    public static function withAddressFieldNames(\Mage_Core_Exception_Input $e, array $prefixes = []): \Mage_Core_Exception_Input
    {
        $names = self::ADDRESS_FIELD_NAMES;
        foreach (array_unique(array_column($e->getErrors(), 'field')) as $field) {
            foreach ($prefixes as $prefix => $bodyName) {
                if (str_starts_with($field, $prefix . '.')) {
                    $addressField = substr($field, strlen($prefix) + 1);
                    $names[$field] = $bodyName . '.' . (self::ADDRESS_FIELD_NAMES[$addressField] ?? $addressField);
                }
            }
        }

        return $e->withFieldNames($names);
    }

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
            $args = RequestInput::decode($request);
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
            $cartId = RequestInput::toInt($args['cartId'] ?? null);
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
            $quote = $cartId ? $service->findById($cartId) : null;
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
