<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

/**
 * SalesRule Coupon Model
 *
 * @package    Mage_SalesRule
 *
 * @method Mage_SalesRule_Model_Resource_Coupon _getResource()
 * @method Mage_SalesRule_Model_Resource_Coupon getResource()
 * @method Mage_SalesRule_Model_Resource_Coupon_Collection getCollection()
 */
class Mage_SalesRule_Model_Coupon extends Mage_Core_Model_Abstract
{
    /**
     * Coupon's owner rule instance
     *
     * @var Mage_SalesRule_Model_Rule
     */
    protected $_rule;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('salesrule/coupon');
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getRuleId() && $this->_rule instanceof Mage_SalesRule_Model_Rule) {
            $this->setRuleId($this->_rule->getId());
        }
        return parent::_beforeSave();
    }

    /**
     * Set rule instance
     *
     * @return $this
     */
    public function setRule(Mage_SalesRule_Model_Rule $rule)
    {
        $this->_rule = $rule;
        return $this;
    }

    /**
     * Load primary coupon for specified rule
     *
     * @param Mage_SalesRule_Model_Rule|int $rule
     * @return $this
     */
    public function loadPrimaryByRule($rule)
    {
        $this->getResource()->loadPrimaryByRule($this, $rule);
        return $this;
    }

    /**
     * Load Shopping Cart Price Rule by coupon code
     *
     * @param string $couponCode
     * @return $this
     */
    public function loadByCode($couponCode)
    {
        $this->load($couponCode, 'code');
        return $this;
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getExpirationDate(): DateTimeInterface|string|null
    {
        return $this->getData('expiration_date');
    }

    public function setExpirationDate(DateTimeInterface|string|null $value): static
    {
        return $this->setData('expiration_date', $value);
    }

    public function getIsPrimary(): ?bool
    {
        $value = $this->getData('is_primary');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPrimary(?bool $value): static
    {
        return $this->setData('is_primary', $value);
    }

    public function getRuleId(): ?int
    {
        $value = $this->getData('rule_id');
        return $value === null ? null : (int) $value;
    }

    public function setRuleId(?int $value): static
    {
        return $this->setData('rule_id', $value);
    }

    public function getTimesUsed(): ?int
    {
        $value = $this->getData('times_used');
        return $value === null ? null : (int) $value;
    }

    public function setTimesUsed(?int $value): static
    {
        return $this->setData('times_used', $value);
    }

    public function getType(): ?int
    {
        $value = $this->getData('type');
        return $value === null ? null : (int) $value;
    }

    public function setType(?int $value): static
    {
        return $this->setData('type', $value);
    }

    public function getUsageLimit(): ?int
    {
        $value = $this->getData('usage_limit');
        return $value === null ? null : (int) $value;
    }

    public function setUsageLimit(?int $value): static
    {
        return $this->setData('usage_limit', $value);
    }

    public function getUsagePerCustomer(): ?int
    {
        $value = $this->getData('usage_per_customer');
        return $value === null ? null : (int) $value;
    }

    public function setUsagePerCustomer(?int $value): static
    {
        return $this->setData('usage_per_customer', $value);
    }

}
