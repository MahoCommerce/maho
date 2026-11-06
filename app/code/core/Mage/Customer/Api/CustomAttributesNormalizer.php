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

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Security\BackendAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A customer, an address, a cart or an order can reach a customer or a guest. For such a caller,
 * customAttributes and customAttributeLabels keep only the attributes that a storefront form shows
 * to the customer group of the caller. The DTO then goes to the next normalizer.
 */
final class CustomAttributesNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const NORMALIZED = 'maho_customer_custom_attributes';

    private ?int $callerGroupId = null;

    public function __construct(private readonly Security $security) {}

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof Customer || $data instanceof Address)
            && !isset($context[self::NORMALIZED][spl_object_id($data)]);
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        $context[self::NORMALIZED][spl_object_id($data)] = true;

        if ($data instanceof Customer && !$this->hasBackendAccess(['customers'])) {
            $codes = array_flip(CustomAttributes::storefrontCodes('customer', $data->groupId ?? $this->getCallerGroupId()));
            $data->customAttributes = $data->customAttributes === null ? null : array_intersect_key($data->customAttributes, $codes);
        } elseif ($data instanceof Address && !$this->hasBackendAccess(['addresses', 'orders'])) {
            $codes = array_flip(CustomAttributes::storefrontCodes('customer_address', $this->getCallerGroupId()));
            $data->customAttributes = $data->customAttributes === null ? null : array_intersect_key($data->customAttributes, $codes);
            $data->customAttributeLabels = $data->customAttributeLabels === null ? null : array_intersect_key($data->customAttributeLabels, $codes);
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Customer::class => false, Address::class => false];
    }

    /**
     * @param list<string> $resourceIds
     */
    private function hasBackendAccess(array $resourceIds): bool
    {
        return array_any($resourceIds, fn($resourceId) => BackendAccess::isGrantedBy($this->security->isGranted(...), $resourceId));
    }

    /**
     * Return the customer group of the customer of the token, or the group of a guest.
     */
    private function getCallerGroupId(): int
    {
        if ($this->callerGroupId === null) {
            $user = $this->security->getUser();
            $customerId = $user instanceof ApiUser ? $user->getCustomerId() : null;
            $this->callerGroupId = $customerId
                ? (int) \Mage::getModel('customer/customer')->load($customerId)->getGroupId()
                : \Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        }
        return $this->callerGroupId;
    }
}
