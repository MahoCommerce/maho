<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Vault_Token extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('maho_paypal/vault_token');
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

        return 'Saved Payment Method';
    }
}
