<?php

/**
 * Makes every REST write refuse a field that its body type does not accept.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * A POST, PUT or PATCH answers 400 with the name of each unknown or read-only field, and of each value of a wrong
 * type, instead of a 200 that ignores them. An operation that must keep an open body opts out with
 * `denormalizationContext: ['allow_extra_attributes' => true]`; a core one goes into OPEN_BODIES instead.
 */
final class StrictBodyResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * Writes whose processor reads keys of the raw body that the resource does not accept: an action body, a JSON
     * list, an alias such as linked_product_id, or a honeypot field. Remove an entry when its processor checks the
     * keys with rejectUnknownFields(). Do not add one for a new operation.
     */
    public const array OPEN_BODIES = [
        'POST /auth/token',
        'POST /carts',
        'POST /carts/{id}/coupon',
        'POST /carts/{id}/giftcards',
        'POST /carts/{id}/items',
        'POST /carts/{id}/place-order',
        'POST /carts/{id}/shipping-methods',
        'POST /contact',
        'POST /coupons/validate',
        'POST /customers/confirm',
        'POST /customers/confirmation-link',
        'POST /customers/create-from-order',
        'POST /customers/forgot-password',
        'POST /customers/me/revocation-requests',
        'POST /customers/me/wishlist/share',
        'POST /customers/me/wishlist/sync',
        'POST /customers/me/wishlist/{id}/move-to-cart',
        'POST /customers/reset-password',
        'POST /guest-carts',
        'POST /guest-carts/{id}/giftcards',
        'POST /guest-carts/{id}/items',
        'POST /guest-carts/{id}/shipping-methods',
        'POST /guest-carts/{maskedQuoteId}/place-order',
        'POST /orders',
        'POST /orders/{id}/cancel',
        'POST /orders/{id}/hold',
        'POST /orders/{id}/unhold',
        'POST /orders/{orderId}/credit-memos',
        'POST /orders/{orderId}/invoices',
        'POST /orders/{orderId}/shipments',
        'POST /products/{productId}/bundle-options',
        'POST /products/{productId}/configurable/children',
        'POST /products/{productId}/custom-options',
        'POST /products/{productId}/downloadable-links',
        'POST /products/{productId}/grouped',
        'POST /products/{productId}/links/cross-sell',
        'POST /products/{productId}/links/related',
        'POST /products/{productId}/links/up-sell',
        'POST /products/{productId}/media',
        'POST /shipments/{id}/comments',
        'POST /shipments/{id}/tracks',
        'PUT /carts/{id}/billing-address',
        'PUT /carts/{id}/gift-message',
        'PUT /carts/{id}/items/{itemId}',
        'PUT /carts/{id}/items/{itemId}/gift-message',
        'PUT /carts/{id}/payment-method',
        'PUT /carts/{id}/shipping-address',
        'PUT /carts/{id}/shipping-method',
        'PUT /guest-carts/{id}/billing-address',
        'PUT /guest-carts/{id}/coupon',
        'PUT /guest-carts/{id}/gift-message',
        'PUT /guest-carts/{id}/items/{itemId}',
        'PUT /guest-carts/{id}/items/{itemId}/gift-message',
        'PUT /guest-carts/{id}/payment-method',
        'PUT /guest-carts/{id}/shipping-address',
        'PUT /guest-carts/{id}/shipping-method',
        'PUT /inventory',
        'PUT /inventory/bulk',
        'PUT /products/{productId}/bundle-options/{id}',
        'PUT /products/{productId}/configurable',
        'PUT /products/{productId}/custom-options/{id}',
        'PUT /products/{productId}/downloadable-links',
        'PUT /products/{productId}/group-prices',
        'PUT /products/{productId}/grouped',
        'PUT /products/{productId}/links/cross-sell',
        'PUT /products/{productId}/links/related',
        'PUT /products/{productId}/links/up-sell',
        'PUT /products/{productId}/media',
        'PUT /products/{productId}/tier-prices',
    ];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {}

    #[\Override]
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }
            $changes = [];
            foreach ($operations as $name => $operation) {
                if ($this->isOpenWrite($operation)) {
                    $changes[(string) $name] = $operation->withDenormalizationContext(($operation->getDenormalizationContext() ?? []) + [
                        AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                        DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                        DenormalizerInterface::COLLECT_EXTRA_ATTRIBUTES_ERRORS => true,
                    ]);
                }
            }
            foreach ($changes as $name => $operation) {
                $operations->add($name, $operation);
            }
        }

        return $collection;
    }

    private function isOpenWrite(Operation $operation): bool
    {
        return $operation instanceof HttpOperation
            && in_array(strtoupper($operation->getMethod()), ['POST', 'PUT', 'PATCH'], true)
            && $operation->canDeserialize() !== false
            && !array_key_exists(AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES, $operation->getDenormalizationContext() ?? [])
            && !in_array(strtoupper($operation->getMethod()) . ' ' . $operation->getUriTemplate(), self::OPEN_BODIES, true);
    }
}
