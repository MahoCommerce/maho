<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule model with condition support
 */
class Mage_Payment_Model_Restriction_Rule extends Mage_Rule_Model_Abstract
{
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    public const TYPE_DENYLIST = 'denylist';

    protected function _construct(): void
    {
        $this->_init('payment/restriction');
    }

    protected function _afterLoad()
    {
        $this->getConditions();
        return parent::_afterLoad();
    }

    protected function _beforeSave()
    {
        // Don't auto-serialize here - it's handled in the controller
        return parent::_beforeSave();
    }

    /**
     * Override to use payment restriction condition models
     */
    public function getConditionsInstance(): Mage_Payment_Model_Restriction_Rule_Condition_Combine
    {
        return Mage::getModel('payment/restriction_rule_condition_combine');
    }

    public function getActionsInstance(): void //@phpstan-ignore method.childReturnType
    {
        // We have no "actions" in this module, but this is required by parent
    }

    /**
     * Override parent getConditions to use our condition models
     */
    public function getConditions(): Mage_Rule_Model_Condition_Combine
    {
        if (!$this->_conditions) {
            $this->_conditions = $this->getConditionsInstance();
            $this->_conditions->setRule($this);
            $this->_conditions->setId('1')->setPrefix('conditions');

            // Try to deserialize conditions from database only when first creating conditions
            if ($this->getConditionsSerialized()) {
                try {
                    $conditions = unserialize($this->getConditionsSerialized());
                    if (is_array($conditions) && !empty($conditions)) {
                        $this->_conditions->loadArray($conditions);
                    }
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }

        return $this->_conditions;
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

        foreach ($restrictions as $restriction) {
            // Check if this restriction applies to the payment method
            $paymentMethods = $restriction->getPaymentMethods();
            if ($paymentMethods && !empty(trim($paymentMethods))) {
                $methodCodes = array_map('trim', explode(',', $paymentMethods));
                if (!in_array($paymentMethodCode, $methodCodes)) {
                    continue; // This restriction doesn't apply to this payment method
                }
            }

            // Create the restriction rule and validate conditions
            $restrictionRule = Mage::getModel('payment/restriction_rule');
            $restrictionRule->setData($restriction->getData());

            if ($restrictionRule->validate($quote)) {
                return false; // Denylist rule matched - deny payment method
            }
        }

        return true;
    }

    public function validate(Varien_Object $object): bool
    {
        return $this->getConditions()->validate($object);
    }
}
