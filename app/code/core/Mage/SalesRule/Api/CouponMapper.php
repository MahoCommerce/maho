<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\SalesRule\Api;

final class CouponMapper
{
    private const DISCOUNT_TYPE_MAP = [
        'by_percent' => 'percent',
        'by_fixed' => 'fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    public static function mapToDto(\Mage_SalesRule_Model_Coupon $coupon, \Mage_SalesRule_Model_Rule $rule): Coupon
    {
        $dto = new Coupon();
        $dto->id = (int) $coupon->getId();
        $dto->code = $coupon->getCode();
        $dto->ruleId = (int) $coupon->getRuleId();
        $dto->ruleName = $rule->getName();
        $dto->timesUsed = (int) $coupon->getTimesUsed();

        $dto->discountType = self::DISCOUNT_TYPE_MAP[$rule->getSimpleAction()] ?? $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();
        $dto->description = $rule->getDescription();
        $dto->isActive = (bool) $rule->getIsActive();
        $dto->usageLimit = $rule->getUsesPerCoupon() ? (int) $rule->getUsesPerCoupon() : null;
        $dto->usagePerCustomer = $rule->getUsesPerCustomer() ? (int) $rule->getUsesPerCustomer() : null;
        $dto->fromDate = $rule->getFromDate();
        $dto->toDate = $rule->getToDate();

        $conditions = $rule->getConditions();
        if ($conditions) {
            $dto->minimumSubtotal = self::extractMinimumSubtotal($conditions);
        }

        return $dto;
    }

    private static function extractMinimumSubtotal(\Mage_Rule_Model_Condition_Abstract $conditions): ?float
    {
        foreach ($conditions->getConditions() as $condition) {
            if ($condition->getAttribute() === 'base_subtotal' && $condition->getOperator() === '>=') {
                return (float) $condition->getValue();
            }
        }
        return null;
    }
}
