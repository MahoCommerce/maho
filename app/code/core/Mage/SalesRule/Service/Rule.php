<?php

/**
 * Load, save and delete cart price rules and their coupons, generate coupons and check a coupon code.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;
use Mage_SalesRule_Model_Coupon as Coupon;
use Mage_SalesRule_Model_Rule as Rule;

class Mage_SalesRule_Service_Rule
{
    public const MAX_CODE_LENGTH = 32;
    public const MAX_AFFIX_LENGTH = 32;
    public const XML_PATH_CHECK_RATE_LIMIT = 'system/rate_limit/coupon_validate';
    public const CHECK_RATE_LIMIT_WINDOW = 60;

    /**
     * Return an unsaved rule with the default values of a new rule. A new rule is inactive, so that it does not
     * apply before it is complete.
     */
    public function newRule(): Rule
    {
        /** @var Rule $rule */
        $rule = Mage::getModel('salesrule/rule');
        return $rule->setIsActive(false)
            ->setCouponType(Rule::COUPON_TYPE_NO_COUPON)
            ->setUseAutoGeneration(false)
            ->setSimpleAction(Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(0)
            ->setDiscountStep(0)
            ->setSortOrder(0)
            ->setStopRulesProcessing(false)
            ->setIsRss(false)
            ->setApplyToShipping(false)
            ->setSimpleFreeShipping(0)
            ->setUsesPerCoupon(0)
            ->setUsesPerCustomer(0);
    }

    /**
     * Load the rule $id. With $websiteIds, a rule that has none of these websites does not exist for the caller.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?array $websiteIds = null): Rule
    {
        /** @var Rule $rule */
        $rule = Mage::getModel('salesrule/rule');
        if ($id > 0) {
            $rule->load($id);
        }
        if (!$rule->getId() || !$this->hasWebsite($rule, $websiteIds)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('salesrule')->__('Cart price rule not found'));
        }
        return $rule;
    }

    /**
     * Save $rule. The transport puts its input into the rule first. $inputErrors holds the values that the
     * transport could not read: they come back together with the problems of the rule. A rule with a specific
     * coupon needs its code.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem
     * @throws Mage_Core_Exception_Conflict when another coupon has the code
     */
    public function save(Rule $rule, ?Mage_Core_Exception_Input $inputErrors = null): Rule
    {
        $inputErrors ??= new Mage_Core_Exception_Input();
        if ((int) $rule->getCouponType() === Rule::COUPON_TYPE_SPECIFIC && !$rule->getUseAutoGeneration() && trim((string) $rule->getCouponCode()) === '') {
            $inputErrors->addError('coupon_code', Mage::helper('salesrule')->__('The coupon code is required for a specific coupon.'));
        }
        if ($inputErrors->getErrors() !== []) {
            $rule->validateRecord($inputErrors)->throwIfErrors();
        }
        $rule->save();
        return $rule;
    }

    /**
     * Delete $rule and all its coupons.
     */
    public function delete(Rule $rule): void
    {
        $rule->delete();
    }

    /**
     * Generate $qty coupons for $rule. A setting that is null takes its default from the configuration. Each
     * coupon gets the uses per coupon and per customer of the rule.
     *
     * @return int the number of new coupons
     * @throws Mage_Core_Exception_Conflict when the rule does not use generated coupons
     * @throws Mage_Core_Exception_Input with one error for each invalid setting
     */
    public function generateCoupons(
        Rule $rule,
        int $qty,
        ?int $length = null,
        ?string $format = null,
        ?string $prefix = null,
        ?string $suffix = null,
        ?int $dash = null,
    ): int {
        $helper = Mage::helper('salesrule');
        if (!$rule->getUseAutoGeneration() && (int) $rule->getCouponType() !== Rule::COUPON_TYPE_AUTO) {
            throw new Mage_Core_Exception_Conflict($helper->__('Only a rule with generated coupons can generate coupons.'));
        }

        /** @var Mage_SalesRule_Helper_Coupon $couponHelper */
        $couponHelper = Mage::helper('salesrule/coupon');
        $formats = array_keys($couponHelper->getFormatsList());
        $length ??= max(1, (int) $couponHelper->getDefaultLength());
        $defaultFormat = (string) $couponHelper->getDefaultFormat();
        $format ??= in_array($defaultFormat, $formats, true) ? $defaultFormat : Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC;
        $affixes = ['prefix' => $prefix ?? (string) $couponHelper->getDefaultPrefix(), 'suffix' => $suffix ?? (string) $couponHelper->getDefaultSuffix()];
        $dash ??= (int) $couponHelper->getDefaultDashInterval();

        $errors = new Mage_Core_Exception_Input();
        if ($qty < 1) {
            $errors->addError('qty', $helper->__('The quantity must be 1 or more.'));
        }
        if ($length < 1 || $length > self::MAX_CODE_LENGTH) {
            $errors->addError('length', $helper->__('The code length must be from 1 to %d.', self::MAX_CODE_LENGTH));
        }
        if (!in_array($format, $formats, true)) {
            $errors->addError('format', $helper->__('The code format must be one of: %s.', implode(', ', $formats)));
        }
        foreach ($affixes as $field => $affix) {
            if (!preg_match('/^[A-Za-z0-9_-]{0,' . self::MAX_AFFIX_LENGTH . '}$/', $affix)) {
                $errors->addError($field, $helper->__('The %s must have at most %d characters of A-Z, a-z, 0-9, _ and -.', $field, self::MAX_AFFIX_LENGTH));
            }
        }
        if ($dash < 0 || $dash > $length) {
            $errors->addError('dash', $helper->__('The dash interval must be from 0 to the code length.'));
        }
        $errors->throwIfErrors();

        // A new instance, because the singleton of the rule keeps the settings of an earlier generation
        /** @var Mage_SalesRule_Model_Coupon_Massgenerator $generator */
        $generator = Mage::getModel('salesrule/coupon_massgenerator');
        $generator->setData($affixes + [
            'rule_id' => (int) $rule->getId(),
            'qty' => $qty,
            'length' => $length,
            'format' => $format,
            'dash' => $dash,
            'uses_per_coupon' => $rule->getUsesPerCoupon() ?: null,
            'uses_per_customer' => $rule->getUsesPerCustomer() ?: null,
        ]);

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            $generator->generatePool();
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
        return (int) $generator->getGeneratedCount();
    }

    /**
     * Delete the coupons $couponIds of $rule. Coupons of other rules and the primary coupon stay.
     *
     * @param list<int> $couponIds
     * @return int the number of deleted coupons
     */
    public function deleteCoupons(Rule $rule, array $couponIds): int
    {
        if ($couponIds === []) {
            return 0;
        }
        /** @var Mage_SalesRule_Model_Resource_Coupon_Collection $coupons */
        $coupons = Mage::getResourceModel('salesrule/coupon_collection');
        $coupons->addRuleToFilter($rule)->addFieldToFilter('coupon_id', ['in' => array_values(array_unique($couponIds))]);
        $coupons->getSelect()->where('(main_table.is_primary IS NULL OR main_table.is_primary = 0)');
        $count = 0;
        foreach ($coupons as $coupon) {
            $coupon->delete();
            $count++;
        }
        return $count;
    }

    /**
     * Delete the generated coupon $couponId of $rule. The primary coupon changes only with the rule.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when $rule has no coupon $couponId
     * @throws Mage_Core_Exception_Conflict for the primary coupon
     */
    public function deleteGeneratedCoupon(Rule $rule, int $couponId): Coupon
    {
        $coupon = $this->getCouponById($couponId);
        if ($coupon->getRuleId() !== (int) $rule->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('salesrule')->__('Coupon not found'));
        }
        if ($coupon->getIsPrimary()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('salesrule')->__('The primary coupon changes only with the coupon code or the coupon type of the rule.'));
        }
        $coupon->delete();
        return $coupon;
    }

    /**
     * Load the coupon $id. With $websiteIds, a coupon whose rule has none of these websites does not exist for the caller.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getCouponById(int $id, ?array $websiteIds = null): Coupon
    {
        /** @var Coupon $coupon */
        $coupon = Mage::getModel('salesrule/coupon');
        if ($id > 0) {
            $coupon->load($id);
        }
        if (!$coupon->getId()
            || ($websiteIds !== null && !$this->hasWebsite(Mage::getModel('salesrule/rule')->load($coupon->getRuleId()), $websiteIds))
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('salesrule')->__('Coupon not found'));
        }
        return $coupon;
    }

    /**
     * Save $rule and its coupon $coupon in one transaction. The code of the primary coupon is the code of the
     * rule, so a new primary coupon or a new code of the primary coupon goes into the rule. A coupon gets the uses
     * per coupon and per customer of its rule.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem, the problems of $inputErrors included
     * @throws Mage_Core_Exception_Conflict when another coupon has the code
     */
    public function saveCoupon(Coupon $coupon, Rule $rule, ?Mage_Core_Exception_Input $inputErrors = null): Coupon
    {
        if ($coupon->getIsPrimary()) {
            $rule->setCouponType(Rule::COUPON_TYPE_SPECIFIC)->setUseAutoGeneration(false)->setCouponCode($coupon->getCode());
        } elseif ($coupon->dataHasChangedFor('code') && !preg_match(Rule::COUPON_CODE_PATTERN, (string) $coupon->getCode())) {
            $inputErrors ??= new Mage_Core_Exception_Input();
            $inputErrors->addError('code', Mage::helper('salesrule')->__('The coupon code must have 3 to 64 characters of A-Z, a-z, 0-9, _ and -.'));
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            $this->save($rule, $inputErrors);
            // The rule saved its primary coupon, so this save changes the same row
            $coupon->setId($coupon->getIsPrimary() ? $rule->getPrimaryCoupon()->getId() : $coupon->getId())
                ->setRuleId((int) $rule->getId())
                ->setUsageLimit($rule->getUsesPerCoupon() ?: null)
                ->setUsagePerCustomer($rule->getUsesPerCustomer() ?: null)
                ->save();
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
        return $coupon;
    }

    /**
     * Delete $coupon. The primary coupon of a rule is the code of the rule, so its delete also deletes the rule.
     */
    public function deleteCoupon(Coupon $coupon): void
    {
        /** @var Rule $rule */
        $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
        if ($rule->getId() && $coupon->getIsPrimary()) {
            $rule->delete();
        } else {
            $coupon->delete();
        }
    }

    /**
     * Return the coupon $code when a customer of the group $customerGroupId on the website $websiteId can use it
     * now: its rule is active, in its dates, for the website and the group, and has uses left. Without a group,
     * the group does not count. With $customerId, the uses of that customer count too, and that customer has
     * a limit of checks of their own, on top of the limit of the client.
     *
     * @throws Mage_Core_Exception_Input when the code is empty
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the limit of checks
     * @throws Mage_SalesRule_Exception with the reason when the coupon cannot be used
     */
    public function checkCoupon(string $code, int $websiteId, ?int $customerGroupId = null, ?int $customerId = null): Coupon
    {
        $helper = Mage::helper('salesrule');
        if (trim($code) === '') {
            throw new Mage_Core_Exception_Input()->addError('code', $helper->__('Coupon code is required'));
        }

        // The limit of the client stops a bulk check of generated codes, and the limit of the customer stops an
        // account behind many addresses
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_CHECK_RATE_LIMIT);
        $limiters = [Mage::helper('core')->rateLimiter('coupon_validate', $limit, self::CHECK_RATE_LIMIT_WINDOW, RateLimitScope::Ip)];
        if ($customerId !== null) {
            $limiters[] = Mage::helper('core')->rateLimiterBy('coupon_validate', 'coupon_validate:customer:' . $customerId, $limit, self::CHECK_RATE_LIMIT_WINDOW);
        }
        foreach ($limiters as $limiter) {
            if (!$limiter->attempt()) {
                throw new Mage_Core_Exception_TooManyRequests($helper->__('Too many requests. Please try again later.'), self::CHECK_RATE_LIMIT_WINDOW);
            }
        }

        /** @var Coupon $coupon */
        $coupon = Mage::getModel('salesrule/coupon');
        $coupon->loadByCode($code);
        if (!$coupon->getId()) {
            throw new Mage_SalesRule_Exception($helper->__('Coupon code not found'));
        }

        /** @var Rule $rule */
        $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
        $locale = Mage::app()->getLocale();
        $reason = match (true) {
            !$rule->getId() || !$rule->getIsActive() => 'Coupon is not active',
            !$this->hasWebsite($rule, [$websiteId]) => 'Coupon is not valid for this website',
            $customerGroupId !== null && !in_array($customerGroupId, array_map(intval(...), $rule->getCustomerGroupIds()), true)
                => 'Coupon is not valid for this customer group',
            !$locale->isStoreDateInInterval(null, dateFrom: $rule->getFromDate()) => 'Coupon is not yet active',
            !$locale->isStoreDateInInterval(null, dateTo: $rule->getToDate()),
            $coupon->getData('expiration_date') && $coupon->getData('expiration_date') < $locale->formatDateForDb('now') => 'Coupon has expired',
            $coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit(),
            $customerId !== null && $this->hasNoUsesLeft($rule, $coupon, $customerId) => 'Coupon usage limit reached',
            default => null,
        };
        if ($reason !== null) {
            throw new Mage_SalesRule_Exception($helper->__($reason));
        }
        return $coupon;
    }

    /**
     * Tell whether the customer $customerId used $coupon or its rule as often as the rule allows.
     */
    private function hasNoUsesLeft(Rule $rule, Coupon $coupon, int $customerId): bool
    {
        if ($coupon->getUsagePerCustomer()) {
            $usage = new \Maho\DataObject();
            Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon($usage, $customerId, $coupon->getId());
            if ($usage->getCouponId() && $usage->getTimesUsed() >= $coupon->getUsagePerCustomer()) {
                return true;
            }
        }
        if ($rule->getUsesPerCustomer()) {
            $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $rule->getId());
            return $ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $rule->getUsesPerCustomer();
        }
        return false;
    }

    /**
     * @param list<int>|null $websiteIds
     */
    private function hasWebsite(Rule $rule, ?array $websiteIds): bool
    {
        return $websiteIds === null || array_intersect(array_map(intval(...), (array) $rule->getWebsiteIds()), $websiteIds) !== [];
    }
}
