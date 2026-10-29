<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

class Mage_SalesRule_Model_Observer
{
    /**
     * Registered callback: called before the payment of an order is placed
     *
     * Only an order with a usage limit counts its uses here. Every counter moves in a conditional UPDATE inside one transaction: the
     * rule row is locked first, so concurrent placements of the same rule
     * queue behind it and the second one stops at the limit. A counter at its
     * limit refuses the order. The order placement transaction then rolls back
     * the order and the counters, before the payment runs.
     *
     * @param \Maho\Event\Observer $observer
     * @return $this
     * @throws Mage_Core_Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[Maho\Config\Observer('sales_order_place_before')]
    public function sales_order_beforePlace($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order && $this->_hasUsageLimit($order)) {
            $this->_incrementUsage($order);
            $order->setData('salesrule_usage_counted', true);
        }
        return $this;
    }

    /**
     * Count the uses of an order with no usage limit, after the payment, so the payment
     * does not hold the lock of a rule row that other checkouts share.
     *
     * @param \Maho\Event\Observer $observer
     * @return $this
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[Maho\Config\Observer('sales_order_place_after')]
    public function sales_order_afterPlace($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order && !$order->getData('salesrule_usage_counted')) {
            $this->_incrementUsage($order, false);
        }
        return $this;
    }

    /**
     * Return true when a rule or the coupon of the order has a limit that can refuse the order.
     */
    protected function _hasUsageLimit(Mage_Sales_Model_Order $order): bool
    {
        $customerId = (int) $order->getCustomerId();
        foreach ($customerId ? array_filter(explode(',', (string) $order->getAppliedRuleIds())) : [] as $ruleId) {
            if ((int) Mage::getModel('salesrule/rule')->load((int) $ruleId)->getUsesPerCustomer() > 0) {
                return true;
            }
        }
        if (!$order->getCouponCode()) {
            return false;
        }
        $coupon = Mage::getModel('salesrule/coupon')->loadByCode($order->getCouponCode());
        return (int) $coupon->getUsageLimit() > 0 || ($customerId && (int) $coupon->getUsagePerCustomer() > 0);
    }

    /**
     * @throws Mage_Core_Exception when a counter is at its limit and $applyLimits is true
     */
    protected function _incrementUsage(Mage_Sales_Model_Order $order, bool $applyLimits = true): void
    {
        if (!$order->getAppliedRuleIds() && !$order->getCouponCode()) {
            return;
        }

        // Sorted so concurrent placements take the rule row locks in the same
        // order and queue instead of deadlocking.
        $ruleIds = array_unique(array_filter(array_map(intval(...), explode(',', (string) $order->getAppliedRuleIds()))));
        sort($ruleIds);
        $customerId = (int) $order->getCustomerId();

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            foreach ($ruleIds as $ruleId) {
                $rule = Mage::getModel('salesrule/rule')->load($ruleId);
                if (!$rule->getId()) {
                    continue;
                }
                $rule->getResource()->incrementTimesUsed($ruleId);
                if ($customerId && !Mage::getResourceModel('salesrule/rule_customer')
                    ->incrementTimesUsed($customerId, $ruleId, $applyLimits ? (int) $rule->getUsesPerCustomer() : 0)
                ) {
                    Mage::throwException(Mage::helper('salesrule')->__(
                        'This order has a discount that you used the maximum number of times.',
                    ));
                }
            }

            if ($order->getCouponCode()) {
                $coupon = Mage::getModel('salesrule/coupon')->loadByCode($order->getCouponCode());
                if ($coupon->getId()) {
                    $couponId = (int) $coupon->getId();
                    if (!$coupon->getResource()->incrementTimesUsed($couponId) && $applyLimits) {
                        Mage::throwException(Mage::helper('salesrule')->__(
                            'Coupon code "%s" reached its maximum number of uses.',
                            $order->getCouponCode(),
                        ));
                    }
                    if ($customerId && !Mage::getResourceModel('salesrule/coupon_usage')
                        ->incrementCustomerTimesUsed($customerId, $couponId, $applyLimits ? (int) $coupon->getUsagePerCustomer() : 0)
                    ) {
                        Mage::throwException(Mage::helper('salesrule')->__(
                            'You already used coupon code "%s" the maximum number of times.',
                            $order->getCouponCode(),
                        ));
                    }
                }
            }
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
    }

    /**
     * Registered callback: called after an order payment is canceled
     *
     * @param \Maho\Event\Observer $observer
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[Maho\Config\Observer('sales_order_payment_cancel')]
    public function sales_order_paymentCancel($observer)
    {
        $event = $observer->getEvent();
        /** @var Mage_Sales_Model_Order $order */
        $order = $event->getPayment()->getOrder();

        if (!$order->canCancel()) {
            return;
        }
        $code = $order->getCouponCode();
        if (!$code) {
            return;
        }
        $coupon = Mage::getModel('salesrule/coupon')->loadByCode($code);
        if (!$coupon->getId()) {
            return;
        }
        $couponId = (int) $coupon->getId();
        $ruleId = (int) $coupon->getRuleId();
        $customerId = (int) $order->getCustomerId();

        $coupon->getResource()->decrementTimesUsed($couponId);
        Mage::getResourceModel('salesrule/rule')->decrementTimesUsed($ruleId);
        if ($customerId) {
            Mage::getResourceModel('salesrule/coupon_usage')->decrementCustomerTimesUsed($customerId, $couponId);
            Mage::getResourceModel('salesrule/rule_customer')->decrementTimesUsed($customerId, $ruleId);
        }
    }

    /**
     * Refresh sales coupons report statistics for last day
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @return $this
     */
    #[Maho\Config\CronJob('aggregate_sales_report_coupons_data', configPath: 'reports/crontab/coupons_expr')]
    public function aggregateSalesReportCouponsData($schedule)
    {
        Mage::app()->getLocale()->emulate(0);
        $date = Mage::app()->getLocale()->utcToStore()->modify('-25 hours');
        Mage::getResourceModel('salesrule/report_rule')->aggregate($date);
        Mage::app()->getLocale()->revert();
        return $this;
    }

    /**
     * Check rules that contains affected attribute
     * If rules were found they will be set to inactive and notice will be add to admin session
     *
     * @param string $attributeCode
     * @return $this
     */
    protected function _checkSalesRulesAvailability($attributeCode)
    {
        /** @var Mage_SalesRule_Model_Resource_Rule_Collection $collection */
        $collection = Mage::getResourceModel('salesrule/rule_collection')
            ->addAttributeInConditionFilter($attributeCode);

        $disabledRulesCount = 0;
        foreach ($collection as $rule) {
            /** @var Mage_SalesRule_Model_Rule $rule */
            $rule->setIsActive(0);
            /** @var $rule->getConditions() Mage_SalesRule_Model_Rule_Condition_Combine */
            $this->_removeAttributeFromConditions($rule->getConditions(), $attributeCode);
            $this->_removeAttributeFromConditions($rule->getActions(), $attributeCode);
            $rule->save();

            $disabledRulesCount++;
        }

        if ($disabledRulesCount) {
            Mage::getSingleton('adminhtml/session')->addWarning(
                Mage::helper('salesrule')->__('%d Shopping Cart Price Rules based on "%s" attribute have been disabled.', $disabledRulesCount, $attributeCode),
            );
        }

        return $this;
    }

    /**
     * Remove catalog attribute condition by attribute code from rule conditions
     *
     * @param Mage_Rule_Model_Condition_Combine $combine
     * @param string $attributeCode
     */
    protected function _removeAttributeFromConditions($combine, $attributeCode)
    {
        $conditions = $combine->getConditions();
        foreach ($conditions as $conditionId => $condition) {
            if ($condition instanceof Mage_Rule_Model_Condition_Combine) {
                $this->_removeAttributeFromConditions($condition, $attributeCode);
            }
            if ($condition instanceof Mage_SalesRule_Model_Rule_Condition_Product) {
                if ($condition->getAttribute() == $attributeCode) {
                    unset($conditions[$conditionId]);
                }
            }
        }
        $combine->setConditions($conditions);
    }

    /**
     * After save attribute if it is not used for promo rules already check rules for containing this attribute
     *
     * @return $this
     */
    #[Maho\Config\Observer('catalog_entity_attribute_save_after', area: 'adminhtml')]
    public function catalogAttributeSaveAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Entity_Attribute $attribute */
        $attribute = $observer->getEvent()->getAttribute();
        if ($attribute->dataHasChangedFor('is_used_for_promo_rules') && !$attribute->getIsUsedForPromoRules()) {
            $this->_checkSalesRulesAvailability($attribute->getAttributeCode());
        }

        return $this;
    }

    /**
     * After delete attribute check rules that contains deleted attribute
     * If rules was found they will seted to inactive and added notice to admin session
     *
     * @return $this
     */
    #[Maho\Config\Observer('catalog_entity_attribute_delete_after', area: 'adminhtml')]
    public function catalogAttributeDeleteAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Entity_Attribute $attribute */
        $attribute = $observer->getEvent()->getAttribute();
        if ($attribute->getIsUsedForPromoRules()) {
            $this->_checkSalesRulesAvailability($attribute->getAttributeCode());
        }

        return $this;
    }

    /**
     * Append sales rule product attributes to select by quote item collection
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_quote_config_get_product_attributes')]
    public function addProductAttributes(\Maho\Event\Observer $observer)
    {
        /** @var \Maho\DataObject $attributesTransfer */
        $attributesTransfer = $observer->getEvent()->getAttributes();

        $attributes = Mage::getResourceModel('salesrule/rule')
            ->getActiveAttributes(
                Mage::app()->getWebsite()->getId(),
                Mage::getSingleton('customer/session')->getCustomer()->getGroupId(),
            );
        $result = [];
        foreach ($attributes as $attribute) {
            $result[$attribute['attribute_code']] = true;
        }
        $attributesTransfer->addData($result);
        return $this;
    }

    /**
     * Add coupon's rule name to order data
     *
     * @param \Maho\Event\Observer $observer
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_quote_to_order')]
    public function addSalesRuleNameToOrder($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $couponCode = $order->getCouponCode();

        if (empty($couponCode)) {
            return $this;
        }

        /**
         * @var Mage_SalesRule_Model_Coupon $couponModel
         */
        $couponModel = Mage::getModel('salesrule/coupon');
        $couponModel->loadByCode($couponCode);

        $ruleId = $couponModel->getRuleId();

        if (empty($ruleId)) {
            return $this;
        }

        /**
         * @var Mage_SalesRule_Model_Rule $ruleModel
         */
        $ruleModel = Mage::getModel('salesrule/rule');
        $ruleModel->load($ruleId);

        $order->setCouponRuleName($ruleModel->getName());

        return $this;
    }
}
