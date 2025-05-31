<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction model
 */
class Mage_Payment_Model_Restriction extends Mage_Core_Model_Abstract
{
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    public const TYPE_DENYLIST = 'denylist';

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
        ?Mage_Customer_Model_Customer $customer = null
    ): bool {
        $restrictions = $this->getCollection()
            ->addFieldToFilter('status', self::STATUS_ENABLED);

        $isAllowed = true;

        foreach ($restrictions as $restriction) {
            // Check if this restriction applies to the payment method
            $paymentMethods = $restriction->getPaymentMethods();
            if ($paymentMethods && !empty(trim($paymentMethods))) {
                $methodCodes = array_map('trim', explode(',', $paymentMethods));
                if (!in_array($paymentMethodCode, $methodCodes)) {
                    continue; // This restriction doesn't apply to this payment method
                }
            }

            // Check if this restriction's conditions match
            if ($this->_matchesRestriction($restriction, $quote, $customer)) {
                return false; // Denylist rule matched - deny payment method
            }
        }

        return $isAllowed;
    }

    /**
     * Check if quote/customer matches restriction conditions
     */
    protected function _matchesRestriction(
        Mage_Payment_Model_Restriction $restriction,
        ?Mage_Sales_Model_Quote $quote = null,
        ?Mage_Customer_Model_Customer $customer = null
    ): bool {
        // Customer group restriction
        if ($restriction->getCustomerGroups() && $customer) {
            $customerGroupIds = explode(',', $restriction->getCustomerGroups());
            if (!in_array($customer->getGroupId(), $customerGroupIds)) {
                return false;
            }
        } elseif ($restriction->getCustomerGroups() && $quote) {
            $customerGroupIds = explode(',', $restriction->getCustomerGroups());
            if (!in_array($quote->getCustomerGroupId(), $customerGroupIds)) {
                return false;
            }
        }

        // Country restriction
        if ($restriction->getCountries() && $quote) {
            $countries = explode(',', $restriction->getCountries());
            $billingCountry = $quote->getBillingAddress()->getCountryId();
            $shippingCountry = $quote->getShippingAddress()->getCountryId();

            if (!in_array($billingCountry, $countries) && !in_array($shippingCountry, $countries)) {
                return false;
            }
        }

        // Store restriction
        if ($restriction->getStoreIds() && $quote) {
            $storeIds = explode(',', $restriction->getStoreIds());
            if (!in_array($quote->getStoreId(), $storeIds)) {
                return false;
            }
        }

        // Order total restriction
        if ($restriction->getMinOrderTotal() || $restriction->getMaxOrderTotal()) {
            if (!$quote) {
                return false;
            }

            $total = $quote->getBaseGrandTotal();

            if ($restriction->getMinOrderTotal() && $total < $restriction->getMinOrderTotal()) {
                return false;
            }

            if ($restriction->getMaxOrderTotal() && $total > $restriction->getMaxOrderTotal()) {
                return false;
            }
        }

        // Product category restriction
        if ($restriction->getProductCategories() && $quote) {
            $categoryIds = explode(',', $restriction->getProductCategories());
            $hasMatchingCategory = false;

            foreach ($quote->getAllVisibleItems() as $item) {
                $product = $item->getProduct();
                $productCategoryIds = $product->getCategoryIds();

                if (array_intersect($categoryIds, $productCategoryIds)) {
                    $hasMatchingCategory = true;
                    break;
                }
            }

            if (!$hasMatchingCategory) {
                return false;
            }
        }

        // Product SKU restriction
        if ($restriction->getProductSkus() && $quote) {
            $skus = array_map('trim', explode(',', $restriction->getProductSkus()));
            $hasMatchingSku = false;

            foreach ($quote->getAllVisibleItems() as $item) {
                if (in_array($item->getSku(), $skus)) {
                    $hasMatchingSku = true;
                    break;
                }
            }

            if (!$hasMatchingSku) {
                return false;
            }
        }

        // Time-based restriction
        if ($restriction->getTimeRestriction()) {
            $timeData = json_decode($restriction->getTimeRestriction(), true);
            if (!$this->_validateTimeRestriction($timeData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate time-based restrictions
     */
    protected function _validateTimeRestriction(array $timeData): bool
    {
        $now = new DateTime();
        $currentDay = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        // Check day restriction
        if (isset($timeData['days']) && !empty($timeData['days'])) {
            if (!in_array($currentDay, $timeData['days'])) {
                return false;
            }
        }

        // Check time range restriction
        if (isset($timeData['start_time']) && isset($timeData['end_time'])) {
            $startTime = $timeData['start_time'];
            $endTime = $timeData['end_time'];

            if ($currentTime < $startTime || $currentTime > $endTime) {
                return false;
            }
        }

        // Check date range restriction
        if (isset($timeData['start_date']) && isset($timeData['end_date'])) {
            $currentDate = $now->format('Y-m-d');

            if ($currentDate < $timeData['start_date'] || $currentDate > $timeData['end_date']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available restriction types
     */
    public function getRestrictionTypes(): array
    {
        return [
            self::TYPE_DENYLIST => Mage::helper('payment')->__('Denylist (Hide methods)'),
        ];
    }
}
