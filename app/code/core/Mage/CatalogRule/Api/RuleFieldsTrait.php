<?php

/**
 * Check the website and customer group lists of a catalog price rule.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A copy of the same methods of Mage\SalesRule\Api\RuleFieldsTrait: the API kernel loads the
 * classes of one module Api directory at a time, so a trait of another module is not found.
 */
trait RuleFieldsTrait
{
    /**
     * @return int[]
     */
    protected function normalizeWebsiteIds(mixed $value, string $field = 'websiteIds'): array
    {
        $known = array_map(intval(...), array_keys(\Mage::app()->getWebsites()));
        $ids = $this->normalizeIdList($value, $known, $field, 'website');

        // A restricted token may only target websites its store allowlist maps to.
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            foreach ($ids as $id) {
                if (!in_array($id, $allowedWebsiteIds, true)) {
                    throw new AccessDeniedHttpException("Access denied for website: {$id}");
                }
            }
        }

        return $ids;
    }

    /**
     * @return int[]
     */
    protected function normalizeCustomerGroupIds(mixed $value, string $field = 'customerGroupIds'): array
    {
        $known = array_map(intval(...), array_keys(\Mage::getModel('customer/group')->getCollection()->toOptionHash()));
        return $this->normalizeIdList($value, $known, $field, 'customer group');
    }

    /**
     * @param int[] $known
     * @return int[]
     */
    protected function normalizeIdList(mixed $value, array $known, string $field, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new ValidationException("{$field} must be a non-empty array of IDs", $field, 'NotBlank');
        }

        $ids = [];
        foreach ($value as $id) {
            if (!is_numeric($id) || !in_array((int) $id, $known, true)) {
                throw new ValidationException(
                    "Unknown {$label} ID: " . (is_scalar($id) ? (string) $id : gettype($id)),
                    $field,
                    'Choice',
                );
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Return $value as an integer, or null when it is not an integer or a text of an integer.
     */
    protected function readInteger(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? null : $number;
    }
}
