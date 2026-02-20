<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Restriction extends Mage_Core_Model_Abstract
{
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    public const TYPE_DENYLIST = 'denylist';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('payment/restriction');
    }

    /**
     * Validate payment method against restrictions
     */
    public function validatePaymentMethod(
        string $paymentMethodCode,
        ?Mage_Sales_Model_Quote $quote = null,
        ?Mage_Customer_Model_Customer $customer = null,
    ): bool {
        $restrictions = $this->getCollection()
            ->addFieldToFilter('status', self::STATUS_ENABLED);

        foreach ($restrictions as $restriction) {
            // Check if this restriction applies to the payment method
            $paymentMethods = $restriction->getPaymentMethods();
            if ($paymentMethods && !empty(trim($paymentMethods))) {
                $methodCodes = array_map('trim', explode(',', $paymentMethods));
                if (!in_array($paymentMethodCode, $methodCodes)) {
                    continue; // This restriction doesn't apply to this payment method
                }
            }

            // Use the new rule-based validation if conditions are available
            if ($restriction->getConditionsSerialized()) {
                // First check basic restrictions (date, website, customer group)
                if (!$this->_checkBasicRestrictions($restriction, $quote, $customer)) {
                    continue; // Basic restrictions don't match, skip this restriction
                }

                $rule = Mage::getModel('payment/restriction_rule');
                $rule->setData($restriction->getData());

                if ($quote && $rule->validate($quote)) {
                    return false; // Denylist rule matched - deny payment method
                }
            } else {
                // Fallback to legacy validation for backward compatibility
                if ($this->_matchesRestriction($restriction, $quote, $customer)) {
                    return false; // Denylist rule matched - deny payment method
                }
            }
        }

        return true;
    }

    /**
     * Check basic restriction conditions (date, website, customer group)
     */
    protected function _checkBasicRestrictions(
        Mage_Payment_Model_Restriction $restriction,
        ?Mage_Sales_Model_Quote $quote = null,
        ?Mage_Customer_Model_Customer $customer = null,
    ): bool {
        // Date restriction (from_date, to_date)
        if ($restriction->getFromDate() || $restriction->getToDate()) {
            $now = new DateTime();
            $currentDate = $now->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            if ($restriction->getFromDate() && $currentDate < $restriction->getFromDate()) {
                return false;
            }

            if ($restriction->getToDate() && $currentDate > $restriction->getToDate()) {
                return false;
            }
        }

        // Website restriction
        if ($restriction->getWebsites() && $quote) {
            $websiteIds = array_map('trim', explode(',', $restriction->getWebsites()));
            $storeWebsiteId = Mage::app()->getStore($quote->getStoreId())->getWebsiteId();
            if (!in_array($storeWebsiteId, $websiteIds)) {
                return false;
            }
        }

        // Customer group restriction
        if ($restriction->getCustomerGroups() && $customer) {
            $customerGroupIds = array_map('trim', explode(',', $restriction->getCustomerGroups()));
            if (!in_array($customer->getGroupId(), $customerGroupIds)) {
                return false;
            }
        } elseif ($restriction->getCustomerGroups() && $quote) {
            $customerGroupIds = array_map('trim', explode(',', $restriction->getCustomerGroups()));
            if (!in_array($quote->getCustomerGroupId(), $customerGroupIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if quote/customer matches restriction conditions
     */
    protected function _matchesRestriction(
        Mage_Payment_Model_Restriction $restriction,
        ?Mage_Sales_Model_Quote $quote = null,
        ?Mage_Customer_Model_Customer $customer = null,
    ): bool {
        return $this->_checkBasicRestrictions($restriction, $quote, $customer);
    }
}
