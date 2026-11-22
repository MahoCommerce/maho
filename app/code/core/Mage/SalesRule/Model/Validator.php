<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

/**
 * SalesRule Validator Model
 *
 * Allows dispatching before and after events for each controller action
 *
 * @package    Mage_SalesRule
 */
class Mage_SalesRule_Model_Validator extends Mage_Core_Model_Abstract
{
    /**
     * Rule source collection
     *
     * @var Mage_SalesRule_Model_Resource_Rule_Collection
     */
    protected $_rules;

    /**
     * Rounding deltas
     *
     * @var array
     */
    protected $_roundingDeltas = [];

    /**
     * Base rounding deltas
     *
     * @var array
     */
    protected $_baseRoundingDeltas = [];

    /**
     * Quote address
     *
     * @var null|Mage_Sales_Model_Quote_Address
     */
    protected $_address = null;

    /**
     * Defines if method Mage_SalesRule_Model_Validator::reset() wasn't called
     * Used for clearing applied rule ids in Quote and in Address
     *
     * @var bool
     */
    protected $_isFirstTimeResetRun = true;

    /**
     * Information about item totals for rules.
     * @var array
     */
    protected $_rulesItemTotals = [];

    /**
     * Store information about addresses which cart fixed rule applied for
     *
     * @var array
     */
    protected $_cartFixedRuleUsedForAddress = [];

    /**
     * Defines if rule with stop further rules is already applied
     *
     * @var bool
     */
    protected $_stopFurtherRules = false;

    /** @var array<int, list<array{qty: float, rate: float}>> */
    private array $appliedPercentageRules = [];

    private bool $usedNativeProcess = false;

    private const int PERCENTAGE_DISCOUNTS = 0;
    private const int WHOLE_LINE_DISCOUNTS = 1;
    private const int UNKNOWN_DISCOUNT_COVERAGE = 2;

    /** @var array<int, array{float, float}>|null Predicted line discounts, in display and base currency. */
    private ?array $projectedDiscounts = null;

    /** @var array<int, array<int, array{float, float}>> */
    private array $reservedForLaterItems = [];

    private ?float $fixedItemAmount = null;

    /**
     * Init validator
     * Init process load collection of rules for specific website,
     * customer group and coupon code
     *
     * @param   int $websiteId
     * @param   int $customerGroupId
     * @param   string $couponCode
     * @return  Mage_SalesRule_Model_Validator
     */
    public function init($websiteId, $customerGroupId, $couponCode)
    {
        $this->setWebsiteId($websiteId)
            ->setCustomerGroupId($customerGroupId)
            ->setCouponCode($couponCode);

        $key = $websiteId . '_' . $customerGroupId . '_' . $couponCode;
        $this->_rules[$key] ??= Mage::getResourceModel('salesrule/rule_collection')
            ->setValidationFilter($websiteId, $customerGroupId, $couponCode)
            ->load();
        return $this;
    }

    /**
     * Get rules collection for current object state
     *
     * @return Mage_SalesRule_Model_Resource_Rule_Collection
     */
    protected function _getRules()
    {
        $key = $this->getWebsiteId() . '_' . $this->getCustomerGroupId() . '_' . $this->getCouponCode();
        return $this->_rules[$key] ?? null;
    }

    /**
     * Get address object which can be used for discount calculation
     *
     * @return  Mage_Sales_Model_Quote_Address
     */
    protected function _getAddress(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        if ($this->_address) {
            $address = $this->_address;
        } elseif ($item->getQuote()->getItemVirtualQty() > 0) {
            $address = $item->getQuote()->getBillingAddress();
        } else {
            $address = $item->getQuote()->getShippingAddress();
        }
        return $address;
    }

    /**
     * Check if rule can be applied for specific address/quote/customer
     *
     * @param   Mage_SalesRule_Model_Rule $rule
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  bool
     */
    protected function _canProcessRule($rule, $address)
    {
        if ($rule->hasIsValidForAddress($address) && !$address->isObjectNew()) {
            return $rule->getIsValidForAddress($address);
        }

        /**
         * check per coupon usage limit
         */
        if ($rule->getCouponType() != Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON) {
            $couponCode = $address->getQuote()->getCouponCode();
            if (strlen($couponCode)) {
                $coupon = Mage::getModel('salesrule/coupon');
                $coupon->load($couponCode, 'code');
                if ($coupon->getId()) {
                    // check entire usage limit
                    if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
                        $rule->setIsValidForAddress($address, false);
                        return false;
                    }
                    // check coupon expiration
                    if ($coupon->hasExpirationDate() && ($coupon->getExpirationDate() < Mage::app()->getLocale()->formatDateForDb('now'))) {
                        $rule->setIsValidForAddress($address, false);
                        return false;
                    }
                    // check per customer usage limit
                    $customerId = $address->getQuote()->getCustomerId();
                    if ($customerId && $coupon->getUsagePerCustomer()) {
                        $couponUsage = new \Maho\DataObject();
                        Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon(
                            $couponUsage,
                            $customerId,
                            $coupon->getId(),
                        );
                        if ($couponUsage->getCouponId()
                            && $couponUsage->getTimesUsed() >= $coupon->getUsagePerCustomer()
                        ) {
                            $rule->setIsValidForAddress($address, false);
                            return false;
                        }
                    }
                }
            }
        }

        /**
         * check per rule usage limit
         */
        $ruleId = $rule->getId();
        if ($ruleId && $rule->getUsesPerCustomer()) {
            $customerId     = $address->getQuote()->getCustomerId();
            $ruleCustomer   = Mage::getModel('salesrule/rule_customer');
            $ruleCustomer->loadByCustomerRule($customerId, $ruleId);
            if ($ruleCustomer->getId()) {
                if ($ruleCustomer->getTimesUsed() >= $rule->getUsesPerCustomer()) {
                    $rule->setIsValidForAddress($address, false);
                    return false;
                }
            }
        }
        $rule->afterLoad();
        /**
         * quote does not meet rule's conditions
         */
        if (!$rule->validate($address)) {
            $rule->setIsValidForAddress($address, false);
            return false;
        }
        /**
         * passed all validations, remember to be valid
         */
        $rule->setIsValidForAddress($address, true);
        return true;
    }

    /**
     * Quote item free shipping ability check
     * This process not affect information about applied rules, coupon code etc.
     * This information will be added during discount amounts processing
     *
     * @return  Mage_SalesRule_Model_Validator
     */
    public function processFreeShipping(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        $address = $this->_getAddress($item);
        $item->setFreeShipping(false);

        foreach ($this->_getRules() as $rule) {
            /** @var Mage_SalesRule_Model_Rule $rule */
            if (!$this->_canProcessRule($rule, $address)) {
                continue;
            }

            if (!$rule->getActions()->validate($item)) {
                continue;
            }

            switch ($rule->getSimpleFreeShipping()) {
                case Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM:
                    $item->setFreeShipping($rule->getDiscountQty() ?: true);
                    break;

                case Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS:
                    $address->setFreeShipping();
                    break;
            }
            if ($rule->getStopRulesProcessing()) {
                break;
            }
        }
        return $this;
    }

    /**
     * Reset quote and address applied rules
     *
     * @return $this
     */
    public function reset(Mage_Sales_Model_Quote_Address $address)
    {
        if ($this->_isFirstTimeResetRun) {
            $address->setAppliedRuleIds('');
            $address->getQuote()->setAppliedRuleIds('');
            $this->_isFirstTimeResetRun = false;
        }
        $this->_address = $address;
        $this->appliedPercentageRules = [];
        $this->_roundingDeltas = [];
        $this->_baseRoundingDeltas = [];

        return $this;
    }

    /**
     * Quote item discount calculation process
     *
     * @return Mage_SalesRule_Model_Validator
     * @throws Mage_Core_Exception
     */
    public function process(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        $this->usedNativeProcess = true;
        $address = $this->_getAddress($item);
        unset($this->appliedPercentageRules[spl_object_id($item)]);
        $item->setDiscountAmount(0);
        $item->setBaseDiscountAmount(0);
        $item->setDiscountPercent(0);
        $prices = [
            $this->_getItemPrice($item),
            $this->_getItemBasePrice($item),
            $this->_getItemOriginalPrice($item),
            $this->_getItemBaseOriginalPrice($item),
        ];
        $this->_stopFurtherRules = false;
        if ($prices[0] < 0) {
            return $this;
        }
        $appliedRules = [];
        $coverage = self::PERCENTAGE_DISCOUNTS;
        foreach ($this->_getRules() as $rule) {
            if (!$this->_canProcessRule($rule, $address) || !$rule->getActions()->validate($item)) {
                continue;
            }
            $qty = (float) $this->_getItemQty($item, $rule);
            if ($this->projectedDiscounts === null && $rule::class === Mage_SalesRule_Model_Rule::class
                && $rule->getSimpleAction() === Mage_SalesRule_Model_Rule::CART_FIXED_ACTION
            ) {
                $this->projectedDiscounts = $this->projectPriorDiscounts($rule, $appliedRules, $item->getQuote()->getStore());
            }
            $coverage = $this->applyItemRule($item, $rule, $address, $qty, $prices, $coverage);
            $ruleId = $rule->getRuleId();
            $appliedRules[$ruleId] = $ruleId;
            $this->_maintainAddressCouponCode($address, $rule);
            $this->_addDiscountDescription($address, $rule);
            if ($rule->getStopRulesProcessing()) {
                $this->_stopFurtherRules = true;
                break;
            }
        }
        $item->setAppliedRuleIds(implode(',', $appliedRules));
        $address->setAppliedRuleIds($this->mergeIds($address->getAppliedRuleIds(), $appliedRules));
        $quote = $item->getQuote();
        $quote->setAppliedRuleIds($this->mergeIds($quote->getAppliedRuleIds(), $appliedRules));
        return $this;
    }

    /**
     * @param iterable<Mage_Sales_Model_Quote_Item_Abstract> $items
     * @return Generator<int, Mage_Sales_Model_Quote_Item_Abstract>
     */
    public function processItems(iterable $items): Generator
    {
        $processRewritten = static::class !== self::class
            && new ReflectionMethod($this, 'process')->getDeclaringClass()->getName() !== self::class;
        foreach ($this->getDiscountItems($items, resetAmounts: true) as $item) {
            if ($processRewritten) {
                $this->usedNativeProcess = false;
                $item->setAppliedRuleIds('');
            }
            $this->process($item);
            if ($processRewritten && !$this->usedNativeProcess && ($ruleIds = $item->getAppliedRuleIds())) {
                $this->recordPercentageRulesById($item, $ruleIds);
            }
            yield $item;
        }
    }

    private function recordPercentageRulesById(Mage_Sales_Model_Quote_Item_Abstract $item, string $ruleIds): void
    {
        $appliedIds = array_fill_keys(explode(',', $ruleIds), true);
        $itemKey = spl_object_id($item);
        foreach (self::_getRules() ?? [] as $rule) {
            if (!isset($appliedIds[$rule->getRuleId()])) {
                continue;
            }
            $percent = match ($rule->getSimpleAction()) {
                Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION => $rule->getDiscountAmount(),
                Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION => 100 - $rule->getDiscountAmount(),
                default => 0.0,
            };
            if ($percent <= 0) {
                continue;
            }
            $qty = (float) self::_getItemQty($item, $rule);
            $step = $rule->getDiscountStep();
            if ($step) {
                $qty = floor($qty / $step) * $step;
            }
            if ($qty > 0) {
                $this->appliedPercentageRules[$itemKey][] = ['qty' => $qty, 'rate' => min(1.0, $percent / 100)];
            }
        }
    }

    /**
     * @param iterable<Mage_Sales_Model_Quote_Item_Abstract> $items
     * @return Generator<int, Mage_Sales_Model_Quote_Item_Abstract>
     */
    private function getDiscountItems(iterable $items, bool $resetAmounts = false): Generator
    {
        foreach ($items as $item) {
            if ($item->getParentItem() || $item->getParentItemId()) {
                continue;
            }
            $lines = [$item];
            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                if ($resetAmounts) {
                    $item->setDiscountAmount(0);
                    $item->setBaseDiscountAmount(0);
                }
                $lines = $item->getChildren();
            }
            foreach ($lines as $line) {
                if ($item->getNoDiscount() || $line->getNoDiscount()) {
                    if ($resetAmounts) {
                        $line->setDiscountAmount(0);
                        $line->setBaseDiscountAmount(0);
                    }
                    continue;
                }
                yield $line;
            }
        }
    }

    /**
     * @param array{float, float, float, float} $prices
     * @return int Coverage of the discounts applied so far.
     */
    private function applyItemRule(
        Mage_Sales_Model_Quote_Item_Abstract $item,
        Mage_SalesRule_Model_Rule $rule,
        Mage_Sales_Model_Quote_Address $address,
        float $qty,
        array $prices,
        int $discountCoverage,
    ): int {
        [$itemPrice, $baseItemPrice, $itemOriginalPrice, $baseItemOriginalPrice] = $prices;
        $quote = $item->getQuote();
        $rulePercent = min(100, $rule->getDiscountAmount());
        $itemKey = spl_object_id($item);
        $totalQty = (float) $item->getTotalQty();

        $discountAmount = 0;
        $baseDiscountAmount = 0;
        $partialPercentCapacity = null;
        $isPercentage = false;
        $cartFixedAllocation = null;
        //discount for original price
        $originalDiscountAmount = 0;
        $baseOriginalDiscountAmount = 0;

        switch ($rule->getSimpleAction()) {
            case Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION:
                $rulePercent = max(0, 100 - $rule->getDiscountAmount());
                //no break;
            case Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION:
                $isPercentage = true;
                $step = $rule->getDiscountStep();
                if ($step) {
                    $qty = floor($qty / $step) * $step;
                }
                $_rulePct = $rulePercent / 100;
                if ($totalQty > 0 && $qty < $totalQty) {
                    $eligibleFraction = $discountCoverage === self::UNKNOWN_DISCOUNT_COVERAGE ? 1.0 : $qty / $totalQty;
                    $previousRules = $this->appliedPercentageRules[$itemKey] ?? [];
                    if ($discountCoverage === self::PERCENTAGE_DISCOUNTS && $previousRules) {
                        $eligibleFraction = $this->getDiscountedQuantity($previousRules, $qty)
                            / $this->getDiscountedQuantity($previousRules, $totalQty);
                    }
                    $priorDiscount = $item->getDiscountAmount() * $eligibleFraction;
                    $priorBaseDiscount = $item->getBaseDiscountAmount() * $eligibleFraction;
                    $partialPercentCapacity = [
                        max(0.0, $qty * $itemPrice - $priorDiscount),
                        max(0.0, $qty * $baseItemPrice - $priorBaseDiscount),
                    ];
                    $discountAmount = $partialPercentCapacity[0] * $_rulePct;
                    $baseDiscountAmount = $partialPercentCapacity[1] * $_rulePct;
                    $originalDiscountAmount = max(0.0, $qty * $itemOriginalPrice - $priorDiscount) * $_rulePct;
                    $baseOriginalDiscountAmount = max(0.0, $qty * $baseItemOriginalPrice - $priorBaseDiscount) * $_rulePct;
                } else {
                    $discountAmount = ($qty * $itemPrice - $item->getDiscountAmount()) * $_rulePct;
                    $baseDiscountAmount = ($qty * $baseItemPrice - $item->getBaseDiscountAmount()) * $_rulePct;
                    $originalDiscountAmount = ($qty * $itemOriginalPrice - $item->getDiscountAmount()) * $_rulePct;
                    $baseOriginalDiscountAmount = ($qty * $baseItemOriginalPrice - $item->getDiscountAmount()) * $_rulePct;
                }

                if ($qty > 0 && $_rulePct > 0) {
                    $this->appliedPercentageRules[$itemKey][] = ['qty' => $qty, 'rate' => min(1.0, $_rulePct)];
                }

                if (!$rule->getDiscountQty() || $rule->getDiscountQty() > $qty) {
                    $discountPercent = min(100, $item->getDiscountPercent() + $rulePercent);
                    $item->setDiscountPercent($discountPercent);
                }
                break;
            case Mage_SalesRule_Model_Rule::TO_FIXED_ACTION:
                $quoteAmount = $quote->getStore()->convertPrice($rule->getDiscountAmount());
                $discountAmount    = $qty * ($itemPrice - $quoteAmount);
                $baseDiscountAmount = $qty * ($baseItemPrice - $rule->getDiscountAmount());
                //get discount for original price
                $originalDiscountAmount    = $qty * ($itemOriginalPrice - $quoteAmount);
                $baseOriginalDiscountAmount = $qty * ($baseItemOriginalPrice - $rule->getDiscountAmount());
                break;

            case Mage_SalesRule_Model_Rule::BY_FIXED_ACTION:
                $step = $rule->getDiscountStep();
                if ($step) {
                    $qty = floor($qty / $step) * $step;
                }
                $quoteAmount        = $quote->getStore()->convertPrice($rule->getDiscountAmount());
                $this->fixedItemAmount = $quoteAmount;
                $discountAmount     = $qty * $quoteAmount;
                $baseDiscountAmount = $qty * $rule->getDiscountAmount();
                break;

            case Mage_SalesRule_Model_Rule::CART_FIXED_ACTION:
                $cartFixedAllocation = $this->getCartFixedShare($item, $rule, $address, $qty, $totalQty, $itemPrice, $baseItemPrice);
                $discountAmount = $cartFixedAllocation['discount'];
                $baseDiscountAmount = $cartFixedAllocation['base_discount'];
                break;

            case Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION:
                $x = $rule->getDiscountStep();
                $y = $rule->getDiscountAmount();
                if (!$x || $y > $x) {
                    break;
                }
                $buyAndDiscountQty = $x + $y;

                $fullRuleQtyPeriod = floor($qty / $buyAndDiscountQty);
                $freeQty  = $qty - $fullRuleQtyPeriod * $buyAndDiscountQty;

                $discountQty = $fullRuleQtyPeriod * $y;
                if ($freeQty > $x) {
                    $discountQty += $freeQty - $x;
                }

                $discountAmount    = $discountQty * $itemPrice;
                $baseDiscountAmount = $discountQty * $baseItemPrice;
                //get discount for original price
                $originalDiscountAmount    = $discountQty * $itemOriginalPrice;
                $baseOriginalDiscountAmount = $discountQty * $baseItemOriginalPrice;
                break;
        }

        $previousDiscountAmount = $item->getDiscountAmount();
        $previousBaseDiscountAmount = $item->getBaseDiscountAmount();
        $result = new \Maho\DataObject([
            'discount_amount'      => $discountAmount,
            'base_discount_amount' => $baseDiscountAmount,
        ]);
        Mage::dispatchEvent('salesrule_validator_process', [
            'rule'    => $rule,
            'item'    => $item,
            'address' => $address,
            'quote'   => $quote,
            'qty'     => $qty,
            'result'  => $result,
        ]);

        $discountOverridden = $discountAmount != $result->getDiscountAmount()
            || $baseDiscountAmount != $result->getBaseDiscountAmount()
            || $previousDiscountAmount !== $item->getDiscountAmount()
            || $previousBaseDiscountAmount !== $item->getBaseDiscountAmount();
        $discountAmount = $result->getDiscountAmount();
        $baseDiscountAmount = $result->getBaseDiscountAmount();

        if ($cartFixedAllocation !== null) {
            [$budget, $baseBudget] = $this->getCartFixedBudget($rule, $address);
            $store = $quote->getStore();
            $itemDiscount = $item->getDiscountAmount();
            $itemBaseDiscount = $item->getBaseDiscountAmount();
            $capacity = $cartFixedAllocation['capacity'];
            $baseCapacity = $cartFixedAllocation['base_capacity'];
            [$rowTotal, $baseRowTotal] = $this->getCartFixedRowTotals($item);
            // An observer can consume part of the line capacity by changing the item itself.
            if ($itemDiscount !== $previousDiscountAmount) {
                $itemDiscount = min($itemDiscount, $rowTotal ?? $itemPrice * $totalQty);
                $capacity = $this->cartFixedCapacity($store, $itemPrice, $totalQty, $qty, (float) $itemDiscount, $rowTotal);
            }
            if ($itemBaseDiscount !== $previousBaseDiscountAmount) {
                $itemBaseDiscount = min($itemBaseDiscount, $baseRowTotal ?? $baseItemPrice * $totalQty);
                $baseCapacity = $this->cartFixedCapacity($store, $baseItemPrice, $totalQty, $qty, (float) $itemBaseDiscount, $baseRowTotal);
            }
            $discountAmount = max(0.0, min($store->roundPrice($discountAmount), $capacity, $budget));
            $baseDiscountAmount = max(0.0, min($store->roundPrice($baseDiscountAmount), $baseCapacity, $baseBudget));
            $this->deductCartFixedDiscount($rule, $address, $discountAmount, $baseDiscountAmount);
            $item->setDiscountAmount($itemDiscount + $discountAmount);
            $item->setBaseDiscountAmount($itemBaseDiscount + $baseDiscountAmount);
            $item->setOriginalDiscountAmount(min($this->floorDiscountCapacity($store, $itemOriginalPrice * $qty), $discountAmount));
            $item->setBaseOriginalDiscountAmount(min($this->floorDiscountCapacity($store, $baseItemOriginalPrice * $qty), $baseDiscountAmount));
            if ($item->getDiscountAmount() === $previousDiscountAmount
                && $item->getBaseDiscountAmount() === $previousBaseDiscountAmount
            ) {
                return $discountCoverage;
            }
            return $discountOverridden
                ? self::UNKNOWN_DISCOUNT_COVERAGE
                : $this->getWholeLineDiscountCoverage($itemKey, $qty, $totalQty, $discountCoverage);
        }

        /**
         * Process "delta" rounding
         */
        $store = $quote->getStore();
        $percentKey = (string) $item->getDiscountPercent();
        if ($percentKey) {
            $discountAmount = $this->roundDiscountWithDelta(
                (float) $discountAmount,
                $percentKey,
                $store,
                $this->_roundingDeltas,
            );
            $baseDiscountAmount = $this->roundDiscountWithDelta(
                (float) $baseDiscountAmount,
                $percentKey,
                $store,
                $this->_baseRoundingDeltas,
            );
        } else {
            $discountAmount     = $store->roundPrice($discountAmount);
            $baseDiscountAmount = $store->roundPrice($baseDiscountAmount);
        }

        /**
         * We can't use row total here because row total not include tax
         * Discount can be applied on price included tax
         */

        $itemDiscountAmount = $item->getDiscountAmount();
        $itemBaseDiscountAmount = $item->getBaseDiscountAmount();

        if ($partialPercentCapacity === null) {
            $discountLimit = $itemPrice * $qty;
            $baseDiscountLimit = $baseItemPrice * $qty;
            if ($rule->getSimpleAction() === Mage_SalesRule_Model_Rule::BY_FIXED_ACTION) {
                // This rule's quantity limit cannot reduce discounts already applied to the line.
                $discountLimit = max($discountLimit, $itemDiscountAmount);
                $baseDiscountLimit = max($baseDiscountLimit, $itemBaseDiscountAmount);
            }
            $discountAmount = min($itemDiscountAmount + $discountAmount, $discountLimit);
            $baseDiscountAmount = min($itemBaseDiscountAmount + $baseDiscountAmount, $baseDiscountLimit);
        } else {
            $discountAmount = $itemDiscountAmount + max(0.0, min(
                $discountAmount,
                $this->floorDiscountCapacity($store, $partialPercentCapacity[0], $itemPrice * $qty),
            ));
            $baseDiscountAmount = $itemBaseDiscountAmount + max(0.0, min(
                $baseDiscountAmount,
                $this->floorDiscountCapacity($store, $partialPercentCapacity[1], $baseItemPrice * $qty),
            ));
        }

        $item->setDiscountAmount($discountAmount);
        $item->setBaseDiscountAmount($baseDiscountAmount);

        $item->setOriginalDiscountAmount($originalDiscountAmount);
        $item->setBaseOriginalDiscountAmount($baseOriginalDiscountAmount);
        if ($discountAmount === $previousDiscountAmount && $baseDiscountAmount === $previousBaseDiscountAmount) {
            return $discountCoverage;
        }
        if ($discountOverridden) {
            return self::UNKNOWN_DISCOUNT_COVERAGE;
        }
        if ($isPercentage) {
            return $discountCoverage === self::WHOLE_LINE_DISCOUNTS && $qty < $totalQty
                ? self::UNKNOWN_DISCOUNT_COVERAGE
                : $discountCoverage;
        }
        return $rule->getSimpleAction() === Mage_SalesRule_Model_Rule::BY_FIXED_ACTION
            ? $this->getWholeLineDiscountCoverage($itemKey, $qty, $totalQty, $discountCoverage)
            : self::UNKNOWN_DISCOUNT_COVERAGE;
    }

    private function getWholeLineDiscountCoverage(int $itemKey, float $qty, float $totalQty, int $discountCoverage): int
    {
        $percentages = $this->appliedPercentageRules[$itemKey] ?? [];
        if ($discountCoverage === self::UNKNOWN_DISCOUNT_COVERAGE || $qty < $totalQty
            || array_any($percentages, static fn(array $rule): bool => $rule['qty'] < $totalQty)
        ) {
            return self::UNKNOWN_DISCOUNT_COVERAGE;
        }
        return self::WHOLE_LINE_DISCOUNTS;
    }

    /** @param list<array{qty: float, rate: float}> $rules */
    private function getDiscountedQuantity(array $rules, float $qty): float
    {
        // Percentages commute on the same units. Start with the smallest covered quantity.
        usort($rules, static fn(array $a, array $b): int => $a['qty'] <=> $b['qty']);
        $discountedQty = 0.0;
        foreach ($rules as $rule) {
            $discountedQty += (min($rule['qty'], $qty) - $discountedQty) * $rule['rate'];
        }
        return $discountedQty;
    }

    /**
     * Apply discount amount to FPT
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract[] $items
     * @return $this
     */
    public function processWeeeAmount(Mage_Sales_Model_Quote_Address $address, $items)
    {
        /** @var Mage_Weee_Helper_Data $helper */
        $helper = Mage::helper('weee');
        $quote = $address->getQuote();
        $store = $quote->getStore();

        if (!$helper->isEnabled($store) || !$helper->isDiscounted($store) || !$this->appliedPercentageRules) {
            return $this;
        }

        /** @var Mage_Tax_Model_Config $config */
        $config = Mage::getSingleton('tax/config');
        /** @var Mage_Tax_Model_Calculation $calculator */
        $calculator = Mage::getSingleton('tax/calculation');
        $request = $helper->isTaxable($store) && $config->discountTax($store)
            ? $calculator->getRateRequest($address, $quote->getBillingAddress(), $quote->getCustomerTaxClassId(), $store)
            : null;
        $applyTaxAfterDiscount = $config->applyTaxAfterDiscount($store);
        $includeInSubtotal = $helper->includeInSubtotal($store);

        $discounts = [];
        $discountableTax = 0.0;
        $baseDiscountableTax = 0.0;
        foreach ($items as $item) {
            $rules = $this->appliedPercentageRules[spl_object_id($item)] ?? [];
            $qty = $item->getTotalQty();
            if (!$rules || $item->getNoDiscount() || $qty <= 0) {
                continue;
            }

            $applied = $helper->getApplied($item);
            if (!$applied) {
                continue;
            }

            $taxFactor = 1.0;
            if ($request !== null) {
                $request->setProductClassId($item->getProduct()->getTaxClassId());
                $taxFactor += $calculator->getRate($request) / 100;
            }
            $rate = $this->getDiscountedQuantity($rules, $qty) / $qty * $taxFactor;
            $discountableFraction = min($qty, max(array_column($rules, 'qty'))) / $qty;

            $totalDiscount = 0.0;
            $totalBaseDiscount = 0.0;
            foreach ($applied as $index => $fee) {
                $discount = $fee['row_amount'] * $rate;
                $baseDiscount = $fee['base_row_amount'] * $rate;
                $applied[$index]['weee_discount'] = $discount;
                $applied[$index]['base_weee_discount'] = $baseDiscount;
                $totalDiscount += $discount;
                $totalBaseDiscount += $baseDiscount;
                $discountableTax += $fee['row_amount'] * $discountableFraction * $taxFactor;
                $baseDiscountableTax += $fee['base_row_amount'] * $discountableFraction * $taxFactor;
            }

            if (!$totalDiscount && !$totalBaseDiscount) {
                continue;
            }

            $discounts[] = [$item, $applied, $totalDiscount, $totalBaseDiscount];
        }

        // Separate percentage rounding carries must not discount more than the eligible fees.
        $remaining = max(0.0, $store->roundPrice($discountableTax));
        $baseRemaining = max(0.0, $store->roundPrice($baseDiscountableTax));
        foreach ($discounts as [$item, $applied, $totalDiscount, $totalBaseDiscount]) {
            $percentage = (string) $item->getDiscountPercent();
            $totalDiscount = min($this->_roundWithDeltas($percentage, $totalDiscount, $store), $remaining);
            $totalBaseDiscount = min($this->_roundWithDeltasForBase($percentage, $totalBaseDiscount, $store), $baseRemaining);
            $remaining = $store->roundPrice($remaining - $totalDiscount);
            $baseRemaining = $store->roundPrice($baseRemaining - $totalBaseDiscount);

            $item->setWeeeDiscount($totalDiscount);
            $item->setBaseWeeeDiscount($totalBaseDiscount);

            if ($includeInSubtotal) {
                $item->setDiscountAmount($item->getDiscountAmount() + $totalDiscount);
                $item->setBaseDiscountAmount($item->getBaseDiscountAmount() + $totalBaseDiscount);
                $address->addTotalAmount('discount', -$totalDiscount);
                $address->addBaseTotalAmount('discount', -$totalBaseDiscount);
                continue;
            }

            $helper->setApplied($item, $helper->allocateTaxDiscounts($applied, $totalDiscount, $totalBaseDiscount, $store));
            $address->setExtraTaxAmount($address->getExtraTaxAmount() - $totalDiscount);
            $address->setBaseExtraTaxAmount($address->getBaseExtraTaxAmount() - $totalBaseDiscount);
            $address->setWeeeDiscount($address->getWeeeDiscount() + $totalDiscount);
            $address->setBaseWeeeDiscount($address->getBaseWeeeDiscount() + $totalBaseDiscount);

            if (!$applyTaxAfterDiscount) {
                $address->addTotalAmount('tax', -$totalDiscount);
                $address->addBaseTotalAmount('tax', -$totalBaseDiscount);
            }
        }
        return $this;
    }

    /**
     * Round the amount with deltas collected
     *
     * @param string $key
     * @param float $amount
     * @param Mage_Core_Model_Store $store
     * @return float
     */
    protected function _roundWithDeltas($key, $amount, $store)
    {
        return $this->roundDiscountWithDelta((float) $amount, (string) $key, $store, $this->_roundingDeltas);
    }

    /**
     * Round the amount with deltas collected
     *
     * @param string $key
     * @param float $amount
     * @param Mage_Core_Model_Store $store
     * @return float
     */
    protected function _roundWithDeltasForBase($key, $amount, $store)
    {
        return $this->roundDiscountWithDelta((float) $amount, (string) $key, $store, $this->_baseRoundingDeltas);
    }

    private function roundDiscountWithDelta(float $amount, string $key, Mage_Core_Model_Store $store, array &$deltas): float
    {
        if ($amount === 0.0) {
            return 0.0;
        }

        $amount = round($amount + ($deltas[$key] ?? 0), 14);
        $roundedAmount = $store->roundPrice($amount);
        $deltas[$key] = round($amount - $roundedAmount, 14);
        return $roundedAmount;
    }

    /**
     * @param array<int, int|string> $appliedRules
     * @return array<int, array{float, float}>
     */
    private function projectPriorDiscounts(Mage_SalesRule_Model_Rule $credit, array $appliedRules, Mage_Core_Model_Store $store): array
    {
        if (static::class !== self::class || $store::class !== Mage_Core_Model_Store::class) {
            return [];
        }
        $prior = null;
        $foundCredit = false;
        $hasLaterRule = false;
        foreach ($this->_getRules() as $rule) {
            if ($rule::class !== Mage_SalesRule_Model_Rule::class) {
                return [];
            }
            $action = $rule->getSimpleAction();
            if ($action === Mage_SalesRule_Model_Rule::CART_FIXED_ACTION) {
                if ($hasLaterRule || !$rule->hasLoadedUnconditionalDiscount()) {
                    return [];
                }
                $foundCredit = true;
            } elseif (in_array($action, [Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, Mage_SalesRule_Model_Rule::BY_FIXED_ACTION], true)) {
                if ($foundCredit) {
                    $hasLaterRule = true;
                } elseif ($prior === null && isset($appliedRules[$rule->getId()]) && $rule->hasLoadedUnconditionalDiscount()) {
                    $prior = $rule;
                } else {
                    return [];
                }
            } else {
                return [];
            }
            if ($rule->getStopRulesProcessing()) {
                break;
            }
        }
        $discounts = [];
        $deltas = [[], []];
        foreach ($this->_rulesItemTotals[$credit->getId()]['lines'] ?? [] as $key => [$price, $basePrice, $totalQty]) {
            if ($price < 0 || $basePrice < 0) {
                return [];
            }
            $discounts[$key] = [0.0, 0.0];
            if ($prior === null) {
                continue;
            }
            $qty = $this->getRuleQty($totalQty, $prior);
            if ($step = $prior->getDiscountStep()) {
                $qty = floor($qty / $step) * $step;
            }
            $amount = $prior->getDiscountAmount();
            $percentage = $prior->getSimpleAction() === Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION;
            $carry = $percentage && (!$prior->getDiscountQty() || $prior->getDiscountQty() > $qty) && $amount > 0;
            // A later rule on this line could change the percentage carry for the next line.
            if ($carry && $hasLaterRule) {
                return [];
            }
            foreach ([$price, $basePrice] as $currency => $unitPrice) {
                $limit = $qty * $unitPrice;
                $raw = $percentage ? $limit * min(1.0, $amount / 100) : $qty * ($currency === 0 ? $this->fixedItemAmount : $amount);
                $discount = $carry
                    ? $this->roundDiscountWithDelta($raw, (string) $amount, $store, $deltas[$currency])
                    : $store->roundPrice($raw);
                if ($percentage && $qty < $totalQty) {
                    $limit = $this->floorDiscountCapacity($store, $limit);
                }
                $discounts[$key][$currency] = min($limit, $discount);
            }
        }
        return $discounts;
    }

    /** @return array{discount: float, base_discount: float, capacity: float, base_capacity: float} */
    private function getCartFixedShare(
        Mage_Sales_Model_Quote_Item_Abstract $item,
        Mage_SalesRule_Model_Rule $rule,
        Mage_Sales_Model_Quote_Address $address,
        float $qty,
        float $totalQty,
        float $price,
        float $basePrice,
    ): array {
        $ruleId = $rule->getId();
        $totals = $this->_rulesItemTotals[$ruleId] ?? null;
        if ($totals === null) {
            Mage::throwException(Mage::helper('salesrule')->__('Item totals are not set for rule.'));
        }
        $store = $address->getQuote()->getStore();
        [$budget, $baseBudget] = $this->getCartFixedBudget($rule, $address);
        [$rowTotal, $baseRowTotal] = $this->getCartFixedRowTotals($item);
        $capacity = $this->cartFixedCapacity($store, $price, $totalQty, $qty, (float) $item->getDiscountAmount(), $rowTotal);
        $baseCapacity = $this->cartFixedCapacity($store, $basePrice, $totalQty, $qty, (float) $item->getBaseDiscountAmount(), $baseRowTotal);
        $amount = $budget;
        $baseAmount = $baseBudget;
        $reserved = $this->reservedForLaterItems[$ruleId] ??= $this->reserveCartFixedShares($totals['lines'] ?? [], [$budget, $baseBudget], $store);
        $key = spl_object_id($item);
        if (isset($reserved[$key])) {
            $amount = max(0.0, $store->roundPrice($budget - $reserved[$key][0]));
            $baseAmount = max(0.0, $store->roundPrice($baseBudget - $reserved[$key][1]));
        } elseif ($totals['items_count'] > 1) {
            $gross = $totals['base_items_price'];
            $share = $gross > 0.0 ? $rule->getDiscountAmount() * $basePrice * $qty / $gross : 0.0;
            $amount = min($budget, $store->roundPrice($store->convertPrice($share)));
            $baseAmount = min($baseBudget, $store->roundPrice($share));
            $this->_rulesItemTotals[$ruleId]['items_count']--;
        }
        return [
            'discount' => min($capacity, $amount),
            'base_discount' => min($baseCapacity, $baseAmount),
            'capacity' => $capacity,
            'base_capacity' => $baseCapacity,
        ];
    }

    /**
     * @param array<int, array{float, float, float, float, ?float, ?float}> $lines
     * @param array{float, float} $budgets
     * @return array<int, array{float, float}>
     */
    private function reserveCartFixedShares(array $lines, array $budgets, Mage_Core_Model_Store $store): array
    {
        if (!$this->projectedDiscounts || array_keys($lines) !== array_keys($this->projectedDiscounts)) {
            return [];
        }
        $reserved = [];
        foreach ($budgets as $currency => $budget) {
            $rows = [];
            foreach ($lines as $key => $line) {
                [$price, $totalQty, $qty, $rowTotal] = [$line[$currency], $line[2], $line[3], $line[$currency + 4]];
                $rows[$key] = [
                    'gross' => max(0.0, $price * $qty),
                    'capacity' => $this->cartFixedCapacity(
                        $store,
                        $price,
                        $totalQty,
                        $qty,
                        $this->projectedDiscounts[$key][$currency],
                        $rowTotal,
                    ),
                ];
            }
            $later = 0.0;
            $shares = $this->allocateCartFixedBudget($budget, $rows, $store);
            foreach (array_reverse($shares, true) as $key => $share) {
                $reserved[$key][$currency] = $later;
                $later = $store->roundPrice($later + $share);
                $this->projectedDiscounts[$key][$currency] += $share;
            }
        }
        return $reserved;
    }

    /** @return array{?float, ?float} */
    private function getCartFixedRowTotals(Mage_Sales_Model_Quote_Item_Abstract $item): array
    {
        // Only use the collected row totals when the discount uses the regular calculation price.
        if (static::class !== self::class || $item::class !== Mage_Sales_Model_Quote_Item::class
            || $item->getDiscountCalculationPrice() !== null
        ) {
            return [null, null];
        }
        return [$item->getRowTotal(), $item->getBaseRowTotal()];
    }

    private function cartFixedCapacity(
        Mage_Core_Model_Store $store,
        float $price,
        float $totalQty,
        float $qty,
        float $discount,
        ?float $rowTotal,
    ): float {
        if ($totalQty > 0 && $qty >= $totalQty && $rowTotal !== null) {
            return $this->floorDiscountCapacity($store, $rowTotal - $discount, $rowTotal);
        }
        $fraction = $totalQty > 0 ? $qty / $totalQty : 0.0;
        return $this->floorDiscountCapacity($store, ($price * $totalQty - $discount) * $fraction, $price * $qty);
    }

    private function floorDiscountCapacity(Mage_Core_Model_Store $store, float $amount, float $gross = 0.0): float
    {
        $amount = max(0.0, $amount);
        $rounded = $store->roundPrice($amount);
        $adjustment = $rounded - $amount;
        if ($adjustment <= 2 * PHP_FLOAT_EPSILON * max($gross, $amount)) {
            return $rounded;
        }

        // Step away from the upper price until the store rounds down.
        do {
            $adjustedAmount = $amount - $adjustment;
            if ($adjustedAmount <= 0.0) {
                return 0.0;
            }
            $rounded = $store->roundPrice($adjustedAmount);
            $adjustment *= 2;
        } while ($rounded > $amount);

        return $rounded;
    }

    /**
     * @param array<int, array{gross: float, capacity: float}> $rows
     * @return array<int, float>
     */
    private function allocateCartFixedBudget(float $budget, array $rows, Mage_Core_Model_Store $store): array
    {
        $gross = array_sum(array_column($rows, 'gross'));
        $remaining = $store->roundPrice(min($budget, array_sum(array_column($rows, 'capacity'))));
        $unallocated = $remaining;
        $ratios = [];
        foreach ($rows as $index => $row) {
            $ratios[$index] = $row['gross'] > 0.0 ? $row['capacity'] / $row['gross'] : 0.0;
        }
        asort($ratios, SORT_NUMERIC);

        // Recalculate the proportional share after removing rows that cannot absorb it.
        $cappedAmounts = [];
        foreach ($ratios as $index => $ratio) {
            if ($gross <= 0.0 || $ratio >= $remaining / $gross) {
                break;
            }
            $cappedAmounts[$index] = $rows[$index]['capacity'];
            $remaining = $store->roundPrice(max(0.0, $remaining - $cappedAmounts[$index]));
            $gross -= $rows[$index]['gross'];
        }

        $amounts = [];
        foreach ($rows as $index => $row) {
            $share = $cappedAmounts[$index] ?? ($gross > 0.0 ? $store->roundPrice($remaining * $row['gross'] / $gross) : 0.0);
            $amounts[$index] = min($share, $row['capacity'], $unallocated);
            $unallocated = $store->roundPrice($unallocated - $amounts[$index]);
        }

        foreach (array_reverse($rows, true) as $index => $row) {
            if ($unallocated <= 0.0) {
                break;
            }
            $extra = min($unallocated, $store->roundPrice($row['capacity'] - $amounts[$index]));
            $amounts[$index] = $store->roundPrice($amounts[$index] + $extra);
            $unallocated = $store->roundPrice($unallocated - $extra);
        }
        return $amounts;
    }

    /** @return array{float, float} */
    private function getCartFixedBudget(Mage_SalesRule_Model_Rule $rule, Mage_Sales_Model_Quote_Address $address): array
    {
        $store = $address->getQuote()->getStore();
        $ruleId = $rule->getId();
        $amount = $rule->getDiscountAmount();
        return [
            $address->getData('cart_fixed_display_rules')[$ruleId] ?? $store->roundPrice($store->convertPrice($amount)),
            $address->getCartFixedRules()[$ruleId] ?? $store->roundPrice($amount),
        ];
    }

    private function deductCartFixedDiscount(
        Mage_SalesRule_Model_Rule $rule,
        Mage_Sales_Model_Quote_Address $address,
        float $discount,
        float $baseDiscount,
    ): void {
        [$budget, $baseBudget] = $this->getCartFixedBudget($rule, $address);
        $store = $address->getQuote()->getStore();
        $displayRules = $address->getData('cart_fixed_display_rules') ?? [];
        $baseRules = $address->getCartFixedRules() ?? [];
        $displayRules[$rule->getId()] = $store->roundPrice(max(0.0, $budget - $discount));
        $baseRules[$rule->getId()] = $store->roundPrice(max(0.0, $baseBudget - $baseDiscount));
        $address->setData('cart_fixed_display_rules', $displayRules);
        $address->setCartFixedRules($baseRules);
    }

    /**
     * Apply discounts to shipping amount
     *
     * @return  Mage_SalesRule_Model_Validator
     */
    public function processShippingAmount(Mage_Sales_Model_Quote_Address $address)
    {
        $shippingAmount = $address->getShippingAmountForDiscount();
        if ($shippingAmount !== null) {
            $baseShippingAmount = $address->getBaseShippingAmountForDiscount();
        } else {
            $shippingAmount     = $address->getShippingAmount();
            $baseShippingAmount = $address->getBaseShippingAmount();
        }
        $quote              = $address->getQuote();
        $appliedRuleIds = [];
        foreach ($this->_getRules() as $rule) {
            /** @var Mage_SalesRule_Model_Rule $rule */
            if (!$rule->getApplyToShipping() || !$this->_canProcessRule($rule, $address)) {
                continue;
            }

            $discountAmount = 0;
            $baseDiscountAmount = 0;
            $rulePercent = min(100, $rule->getDiscountAmount());
            switch ($rule->getSimpleAction()) {
                case Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION:
                    $rulePercent = max(0, 100 - $rule->getDiscountAmount());
                    // no break
                case Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION:
                    $discountAmount    = ($shippingAmount - $address->getShippingDiscountAmount()) * $rulePercent / 100;
                    $baseDiscountAmount = ($baseShippingAmount
                        - $address->getBaseShippingDiscountAmount()) * $rulePercent / 100;
                    $discountPercent = min(100, $address->getShippingDiscountPercent() + $rulePercent);
                    $address->setShippingDiscountPercent($discountPercent);
                    break;
                case Mage_SalesRule_Model_Rule::TO_FIXED_ACTION:
                    $quoteAmount = $quote->getStore()->convertPrice($rule->getDiscountAmount());
                    $discountAmount    = $shippingAmount - $quoteAmount;
                    $baseDiscountAmount = $baseShippingAmount - $rule->getDiscountAmount();
                    break;
                case Mage_SalesRule_Model_Rule::BY_FIXED_ACTION:
                    $quoteAmount        = $quote->getStore()->convertPrice($rule->getDiscountAmount());
                    $discountAmount     = $quoteAmount;
                    $baseDiscountAmount = $rule->getDiscountAmount();
                    break;
                case Mage_SalesRule_Model_Rule::CART_FIXED_ACTION:
                    [$budget, $baseBudget] = $this->getCartFixedBudget($rule, $address);
                    $store = $quote->getStore();
                    $discountAmount = min($budget, $this->floorDiscountCapacity(
                        $store,
                        $shippingAmount - $address->getShippingDiscountAmount(),
                        (float) $shippingAmount,
                    ));
                    $baseDiscountAmount = min($baseBudget, $this->floorDiscountCapacity(
                        $store,
                        $baseShippingAmount - $address->getBaseShippingDiscountAmount(),
                        (float) $baseShippingAmount,
                    ));
                    $this->deductCartFixedDiscount($rule, $address, $discountAmount, $baseDiscountAmount);
                    break;
            }

            $discountAmount     = min($address->getShippingDiscountAmount() + $discountAmount, $shippingAmount);
            $baseDiscountAmount = min(
                $address->getBaseShippingDiscountAmount() + $baseDiscountAmount,
                $baseShippingAmount,
            );
            $address->setShippingDiscountAmount($discountAmount);
            $address->setBaseShippingDiscountAmount($baseDiscountAmount);
            $ruleId = $rule->getRuleId();
            $appliedRuleIds[$ruleId] = $ruleId;

            $this->_maintainAddressCouponCode($address, $rule);
            $this->_addDiscountDescription($address, $rule);
            if ($rule->getStopRulesProcessing()) {
                break;
            }
        }

        $address->setAppliedRuleIds($this->mergeIds($address->getAppliedRuleIds(), $appliedRuleIds));
        $quote->setAppliedRuleIds($this->mergeIds($quote->getAppliedRuleIds(), $appliedRuleIds));

        return $this;
    }

    /**
     * Merge two sets of ids
     *
     * @param array|string $a1
     * @param array|string $a2
     * @param bool $asString
     * @return ($asString is true ? string : array<int, string>)
     */
    public function mergeIds($a1, $a2, $asString = true)
    {
        if (!is_array($a1)) {
            $a1 = empty($a1) ? [] : explode(',', $a1);
        }
        if (!is_array($a2)) {
            $a2 = empty($a2) ? [] : explode(',', $a2);
        }
        $a = array_unique(array_merge($a1, $a2));
        if ($asString) {
            $a = implode(',', $a);
        }
        return $a;
    }

    /**
     * Set information about usage cart fixed rule by quote address
     *
     * @param int $ruleId
     * @param int $itemId
     */
    public function setCartFixedRuleUsedForAddress($ruleId, $itemId): void
    {
        $this->_cartFixedRuleUsedForAddress[$ruleId] = $itemId;
    }

    /**
     * Retrieve information about usage cart fixed rule by quote address
     *
     * @param int $ruleId
     * @return int|null
     */
    public function getCartFixedRuleUsedForAddress($ruleId)
    {
        return $this->_cartFixedRuleUsedForAddress[$ruleId] ?? null;
    }

    /**
     * Calculate quote totals for each rule and save results
     *
     * @param mixed $items
     * @return $this
     */
    public function initTotals($items, Mage_Sales_Model_Quote_Address $address)
    {
        $address->setCartFixedRules([]);
        $address->setData('cart_fixed_display_rules', []);
        $this->_rulesItemTotals = [];
        $this->projectedDiscounts = static::class === self::class ? null : [];
        $this->reservedForLaterItems = [];
        $this->fixedItemAmount = null;

        if (!$items) {
            return $this;
        }

        foreach ($this->_getRules() as $rule) {
            if (Mage_SalesRule_Model_Rule::CART_FIXED_ACTION === $rule->getSimpleAction()
                && $this->_canProcessRule($rule, $address)
            ) {
                $lines = [];
                $ruleTotalItemsPrice = 0;
                $ruleTotalBaseItemsPrice = 0;
                $validItemsCount = 0;

                foreach ($this->getDiscountItems($items) as $item) {
                    if (!$rule->getActions()->validate($item)) {
                        continue;
                    }
                    if ($this->projectedDiscounts === null && $item::class === Mage_Sales_Model_Quote_Item::class) {
                        $totalQty = (float) $item->getTotalQty();
                        $qty = $this->getRuleQty($totalQty, $rule);
                    } else {
                        $this->projectedDiscounts = [];
                        $totalQty = null;
                        $qty = $this->_getItemQty($item, $rule);
                    }
                    $price = $this->_getItemPrice($item);
                    $basePrice = $this->_getItemBasePrice($item);
                    if ($totalQty !== null) {
                        $lines[spl_object_id($item)] = [$price, $basePrice, $totalQty, $qty, ...$this->getCartFixedRowTotals($item)];
                    }
                    $ruleTotalItemsPrice += $price * $qty;
                    $ruleTotalBaseItemsPrice += $basePrice * $qty;
                    $validItemsCount++;
                }

                $this->_rulesItemTotals[$rule->getId()] = [
                    'lines' => $lines,
                    'items_price' => $ruleTotalItemsPrice,
                    'base_items_price' => $ruleTotalBaseItemsPrice,
                    'items_count' => $validItemsCount,
                ];
            }
        }
        $this->_stopFurtherRules = false;
        return $this;
    }

    /**
     * Set coupon code to address if $rule contains validated coupon
     *
     * @param  Mage_Sales_Model_Quote_Address $address
     * @param  Mage_SalesRule_Model_Rule $rule
     *
     * @return $this
     */
    protected function _maintainAddressCouponCode($address, $rule)
    {
        /*
        Rule is a part of rules collection, which includes only rules with 'No Coupon' type or with validated coupon.
        As a result, if rule uses coupon code(s) ('Specific' or 'Auto' Coupon Type), it always contains validated
        coupon
        */
        if ($rule->getCouponType() != Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON) {
            $address->setCouponCode($this->getCouponCode());
        }

        return $this;
    }

    /**
     * Add rule discount description label to address object
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @param   Mage_SalesRule_Model_Rule $rule
     * @return  Mage_SalesRule_Model_Validator
     */
    protected function _addDiscountDescription($address, $rule)
    {
        $description = $address->getDiscountDescriptionArray();
        $ruleLabel = $rule->getStoreLabel($address->getQuote()->getStore());
        $label = '';
        if ($ruleLabel) {
            $label = $ruleLabel;
        } elseif (strlen($address->getCouponCode())) {
            $label = $address->getCouponCode();
        }

        if (strlen($label)) {
            $description[$rule->getId()] = $label;
        }

        $address->setDiscountDescriptionArray($description);

        return $this;
    }

    /**
     * Return item price
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     */
    protected function _getItemPrice($item)
    {
        $price = $item->getDiscountCalculationPrice();
        $calcPrice = $item->getCalculationPrice();
        return $price ?? $calcPrice;
    }

    /**
     * Return item original price
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getItemOriginalPrice($item)
    {
        return Mage::helper('tax')->getPrice($item, $item->getOriginalPrice(), true);
    }

    /**
     * Return item base price
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     */
    protected function _getItemBasePrice($item)
    {
        $price = $item->getDiscountCalculationPrice();
        return ($price !== null) ? $item->getBaseDiscountCalculationPrice() : $item->getBaseCalculationPrice();
    }

    /**
     * Return item base original price
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getItemBaseOriginalPrice($item)
    {
        return Mage::helper('tax')->getPrice($item, $item->getBaseOriginalPrice(), true);
    }

    /**
     * Return discount item qty
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @param Mage_SalesRule_Model_Rule $rule
     * @return int
     */
    protected function _getItemQty($item, $rule)
    {
        $qty = $item->getTotalQty();
        return $rule->getDiscountQty() ? min($qty, $rule->getDiscountQty()) : $qty;
    }

    private function getRuleQty(float $qty, Mage_SalesRule_Model_Rule $rule): float
    {
        return $rule->getDiscountQty() ? min($qty, $rule->getDiscountQty()) : $qty;
    }

    /**
     * Convert address discount description array to string
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param string $separator
     * @return $this
     */
    public function prepareDescription($address, $separator = ', ')
    {
        $descriptionArray = $address->getDiscountDescriptionArray();
        /** @see Mage_SalesRule_Model_Validator::_getAddress */
        if (!$descriptionArray && $address->getQuote()->getItemVirtualQty() > 0) {
            $descriptionArray = $address->getQuote()->getBillingAddress()->getDiscountDescriptionArray();
        }

        $description = $descriptionArray && is_array($descriptionArray)
            ? implode($separator, array_unique($descriptionArray))
            : '';

        $address->setDiscountDescription($description);
        return $this;
    }

    /**
     * wrap Mage::getSingleton
     *
     * @param string $name
     * @return Mage_Core_Model_Abstract|false
     */
    #[\Deprecated(message: 'use Mage::getSingleton()')]
    protected function _getSingleton($name)
    {
        return Mage::getSingleton($name);
    }

    /**
     * wrap Mage::helper
     *
     * @param string $name
     * @return Mage_Core_Helper_Abstract|false
     */
    #[\Deprecated(message: 'use Mage::helper()')]
    protected function _getHelper($name)
    {
        return Mage::helper($name);
    }

    /**
     * Return items list sorted by possibility to apply prioritized rules
     *
     * @param array $items
     * @return array $items
     */
    public function sortItemsByPriority($items)
    {
        $itemsSorted = [];
        foreach ($this->_getRules() as $rule) {
            foreach ($items as $itemKey => $itemValue) {
                if ($rule->getActions()->validate($itemValue)) {
                    unset($items[$itemKey]);
                    $itemsSorted[] = $itemValue;
                }
            }
        }
        if (!empty($itemsSorted)) {
            $items = array_merge($itemsSorted, $items);
        }
        return $items;
    }

    public function getCouponCode(): ?string
    {
        $value = $this->getData('coupon_code');
        return $value === null ? null : (string) $value;
    }

    public function setCouponCode(?string $value): static
    {
        return $this->setData('coupon_code', $value);
    }

    public function getCustomerGroupId(): ?int
    {
        $value = $this->getData('customer_group_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerGroupId(?int $value): static
    {
        return $this->setData('customer_group_id', $value);
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

}
