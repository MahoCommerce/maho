<?php

/**
 * Hides the custom attributes that only the admin sees from a caller without backend access.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use Mage\Sales\Api\Order;
use Maho\ApiPlatform\Security\BackendAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A customer, an address, a cart or an order can reach a customer or a guest. For such a caller,
 * customAttributes and customAttributeLabels keep only the attributes that a storefront form shows
 * to the customer group of the caller, and so do customerCustomAttributes and
 * customerCustomAttributeLabels of an order, for the customer group of the order. The DTO then goes to the next normalizer.
 */
final class CustomAttributesNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const NORMALIZED = 'maho_customer_custom_attributes';

    public function __construct(private readonly Security $security) {}

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof Customer || $data instanceof Address || $data instanceof Order)
            && !isset($context[self::NORMALIZED][spl_object_id($data)]);
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        $context[self::NORMALIZED][spl_object_id($data)] = true;

        if ($data instanceof Customer && !$this->hasBackendAccess(['customers'])) {
            $codes = array_flip(CustomAttributes::storefrontCodes('customer', $data->groupId ?? \Mage::helper('customer')->getCurrentGroupId()));
            $data->customAttributes = $data->customAttributes === null ? null : array_intersect_key($data->customAttributes, $codes);
        } elseif ($data instanceof Address && !$this->hasBackendAccess(['addresses', 'orders'])) {
            $codes = array_flip(CustomAttributes::storefrontCodes('customer_address', \Mage::helper('customer')->getCurrentGroupId()));
            $data->customAttributes = $data->customAttributes === null ? null : array_intersect_key($data->customAttributes, $codes);
            $data->customAttributeLabels = $data->customAttributeLabels === null ? null : array_intersect_key($data->customAttributeLabels, $codes);
        } elseif ($data instanceof Order && !$this->hasBackendAccess(['orders'])) {
            $codes = array_flip(CustomAttributes::storefrontCodes('customer', $data->customerGroupId ?? \Mage::helper('customer')->getCurrentGroupId()));
            $data->customerCustomAttributes = $data->customerCustomAttributes === null ? null : array_intersect_key($data->customerCustomAttributes, $codes);
            $data->customerCustomAttributeLabels = $data->customerCustomAttributeLabels === null ? null : array_intersect_key($data->customerCustomAttributeLabels, $codes);
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Customer::class => false, Address::class => false, Order::class => false];
    }

    /**
     * @param list<string> $resourceIds
     */
    private function hasBackendAccess(array $resourceIds): bool
    {
        return array_any($resourceIds, fn($resourceId) => BackendAccess::isGrantedBy($this->security->isGranted(...), $resourceId));
    }
}
