<?php

/**
 * Drops back-office inventory policy columns from a serialized Product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use Maho\ApiPlatform\Security\BackOfficeAccess;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * `stockItem` is a column-keyed map rather than a typed sub-resource, so
 * `#[ApiProperty(security: ...)]` cannot reach the individual columns; this
 * normalizer applies the same `is_back_office('products')` rule to the keys.
 *
 * The columns it removes describe inventory policy, not availability: the
 * out-of-stock threshold, the admin low-stock alert level, the backorder policy
 * and whether stock is tracked at all, plus every `use_config_*` flag (pure
 * admin config-inheritance metadata). Shopper-relevant columns (qty,
 * is_in_stock, sale quantity bounds, qty increments) stay public.
 */
final class ProductStockItemNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    // Propagates into nested normalizations: fine while variants/linked
    // products are plain arrays, not Product DTOs with their own stockItem.
    private const ALREADY_CALLED = 'maho_product_stock_item_normalizer';

    private const BACK_OFFICE_COLUMNS = [
        'min_qty' => true,
        'notify_stock_qty' => true,
        'backorders' => true,
        'low_stock_date' => true,
        'manage_stock' => true,
    ];

    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {}

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Product && !isset($context[self::ALREADY_CALLED]);
    }

    /** @return array<class-string, bool> false: support depends on the context, so the decision is not cacheable */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Product::class => false];
    }

    /** @return array<array-key, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($data, $format, $context);

        if (!is_array($data)
            || !is_array($data['stockItem'] ?? null)
            || BackOfficeAccess::isGrantedBy($this->authorizationChecker->isGranted(...), 'products')
        ) {
            return $data;
        }

        foreach (array_keys($data['stockItem']) as $column) {
            if (isset(self::BACK_OFFICE_COLUMNS[$column]) || str_starts_with((string) $column, 'use_config_')) {
                unset($data['stockItem'][$column]);
            }
        }

        return $data;
    }
}
