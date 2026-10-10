<?php

/**
 * Check the fields of cart price rules and coupons that more than one API resource writes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Trait\PriceRuleFieldsTrait;

trait RuleFieldsTrait
{
    use PriceRuleFieldsTrait;

    protected function validateCouponCode(mixed $code, string $field = 'code'): string
    {
        if (!is_string($code) || $code === '') {
            throw new ValidationException('Coupon code is required', $field, 'NotBlank');
        }
        if (strlen($code) < 3 || strlen($code) > 64) {
            throw new ValidationException('Coupon code must be between 3 and 64 characters', $field, 'Length');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            throw new ValidationException('Coupon code may only contain alphanumeric characters, dashes, and underscores', $field, 'Regex');
        }
        return $code;
    }

    /**
     * Tell whether a coupon other than $exceptCouponId has the code $code. Letter case does not matter,
     * because the comparison of codes depends on the collation on MySQL.
     */
    protected function isCouponCodeTaken(string $code, ?int $exceptCouponId = null): bool
    {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('salesrule/coupon'), ['coupon_id'])
            ->where('LOWER(code) = ?', strtolower($code))
            ->limit(1);
        if ($exceptCouponId !== null) {
            $select->where('coupon_id <> ?', $exceptCouponId);
        }
        return $adapter->fetchOne($select) !== false;
    }

    protected function normalizeSimpleFreeShipping(mixed $value, string $field = 'simpleFreeShipping'): int
    {
        $valid = [
            0,
            \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM,
            \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS,
        ];
        if (!is_numeric($value) || !in_array((int) $value, $valid, true)) {
            throw new ValidationException('simpleFreeShipping must be 0 (no), 1 (matching items) or 2 (whole shipment)', $field, 'Choice');
        }
        return (int) $value;
    }
}
