<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

/**
 * SalesRule Rule Customer Model
 *
 * @package    Mage_SalesRule
 *
 * @method Mage_SalesRule_Model_Resource_Rule_Customer _getResource()
 * @method Mage_SalesRule_Model_Resource_Rule_Customer getResource()
 */
class Mage_SalesRule_Model_Rule_Customer extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('salesrule/rule_customer');
    }

    /**
     * @param int $customerId
     * @param int $ruleId
     * @return $this
     */
    public function loadByCustomerRule($customerId, $ruleId)
    {
        $this->_getResource()->loadByCustomerRule($this, $customerId, $ruleId);
        return $this;
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
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

}
