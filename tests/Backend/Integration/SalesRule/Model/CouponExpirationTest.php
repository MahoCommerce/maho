<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function couponExpirationStoreDate(string $modify = 'now'): string
{
    return Mage::app()->getLocale()->utcToStore()->modify($modify)->format(Mage_Core_Model_Locale::DATE_FORMAT);
}

function couponExpirationRule(
    ?string $toDate,
    int $couponType = Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC,
    bool $useAutoGeneration = false,
): Mage_SalesRule_Model_Rule {
    $rule = Mage::getModel('salesrule/rule');
    $rule->setName('Coupon expiration ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setCouponType($couponType)
        ->setToDate($toDate)
        ->setUseAutoGeneration($useAutoGeneration)
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10);
    if ($couponType === Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC && !$useAutoGeneration) {
        $rule->setCouponCode('EXPIRE-' . strtoupper(uniqid()));
    }
    $rule->save();
    return $rule;
}

function couponExpirationGenerate(Mage_SalesRule_Model_Rule $rule): int
{
    $generator = Mage::getModel('salesrule/coupon_massgenerator');
    $generator->setData([
        'rule_id' => (int) $rule->getId(),
        'qty' => 1,
        'length' => 12,
        'format' => Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC,
    ]);
    $generator->generatePool();

    return (int) Mage::getResourceModel('salesrule/coupon_collection')
        ->addRuleToFilter($rule)
        ->getFirstItem()
        ->getId();
}

function couponExpirationApplies(Mage_SalesRule_Model_Rule $rule, string $couponCode): bool
{
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCouponCode($couponCode);
    $canProcessRule = new ReflectionMethod(Mage_SalesRule_Model_Validator::class, '_canProcessRule');

    return $canProcessRule->invoke(Mage::getModel('salesrule/validator'), $rule, $quote->getShippingAddress());
}

function couponExpirationStored(int $couponId): ?string
{
    $value = Mage::getModel('salesrule/coupon')->load($couponId)->getData('expiration_date');
    return $value === null ? null : substr((string) $value, 0, 19);
}

describe('the coupon expiration of a rule with a To date', function () {
    test('the coupon of a rule that ends today applies for the whole day', function () {
        $rule = couponExpirationRule(couponExpirationStoreDate());

        expect(couponExpirationApplies($rule, $rule->getCouponCode()))->toBeTrue();
    });

    test('the cart reads the coupon expiration as a UTC time', function (string $timezone, string $expiresIn, bool $applies) {
        $store = Mage::app()->getStore();
        $originalTimezone = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $timezone);
        try {
            $rule = couponExpirationRule(null);
            $rule->getPrimaryCoupon()->setExpirationDate(gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, strtotime($expiresIn)))->save();

            expect(couponExpirationApplies($rule, $rule->getCouponCode()))->toBe($applies);
        } finally {
            $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $originalTimezone);
        }
    })->with([
        'valid for one more hour in a store ahead of UTC' => ['Pacific/Kiritimati', '+1 hour', true],
        'expired one hour ago in a store behind UTC' => ['Etc/GMT+12', '-1 hour', false],
    ]);

    test('a coupon does not copy the To date of its rule', function (Closure $createCoupon) {
        expect(couponExpirationStored($createCoupon(couponExpirationStoreDate())))->toBeNull();
    })->with([
        'primary coupon' => [fn(string $toDate) => (int) couponExpirationRule($toDate)->getPrimaryCoupon()->getId()],
        'acquireCoupon' => [fn(string $toDate) => (int) couponExpirationRule($toDate, Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO)->acquireCoupon()->getId()],
        'generated coupon' => [fn(string $toDate) => couponExpirationGenerate(couponExpirationRule($toDate, useAutoGeneration: true))],
    ]);

    test('a change of the To date keeps the expiration set on a coupon', function () {
        $rule = couponExpirationRule(couponExpirationStoreDate(), useAutoGeneration: true);
        $couponId = couponExpirationGenerate($rule);
        Mage::getModel('salesrule/coupon')->load($couponId)->setExpirationDate('2031-05-05 12:00:00')->save();

        $rule->setToDate(couponExpirationStoreDate('+1 day'))->save();

        expect(couponExpirationStored($couponId))->toBe('2031-05-05 12:00:00');
    });

    test('the upgrade removes a copied To date and keeps an expiration set on the coupon', function () {
        $today = couponExpirationStoreDate();
        $rule = couponExpirationRule($today);
        $explicitRule = couponExpirationRule($today);
        $couponId = (int) $rule->getPrimaryCoupon()->getId();
        $explicitCouponId = (int) $explicitRule->getPrimaryCoupon()->getId();

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $couponTable = Mage::getSingleton('core/resource')->getTableName('salesrule/coupon');
        $connection->update($couponTable, ['expiration_date' => "$today 00:00:00"], ['coupon_id = ?' => $couponId]);
        $connection->update($couponTable, ['expiration_date' => "$today 12:00:00"], ['coupon_id = ?' => $explicitCouponId]);

        $installer = new Mage_Sales_Model_Resource_Setup('salesrule_setup');
        $script = Mage::getBaseDir('code') . '/core/Mage/SalesRule/data/salesrule_setup/data-upgrade-2.0.0-2.0.1.php';
        Closure::bind(fn() => include $script, $installer, $installer::class)();

        expect(couponExpirationStored($couponId))->toBeNull()
            ->and(couponExpirationStored($explicitCouponId))->toBe("$today 12:00:00");
    });
});
