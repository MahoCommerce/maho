<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Vault_Token extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('paypal/vault_token');
    }

    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if ($this->isObjectNew() && !$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);
        return $this;
    }

    public function getDisplayLabel(): string
    {
        if ($this->getLabel()) {
            return (string) $this->getLabel();
        }

        if ($this->getPaymentSourceType() === 'card') {
            $brand = $this->getCardBrand() ? strtoupper($this->getCardBrand()) : 'Card';
            return "{$brand} ending in {$this->getCardLastFour()}";
        }

        if ($this->getPayerEmail()) {
            return "PayPal ({$this->getPayerEmail()})";
        }

        return Mage::helper('paypal')->__('Saved Payment Method');
    }
}
