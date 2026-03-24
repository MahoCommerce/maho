<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Resource_Vault_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('maho_paypal/vault_token', 'token_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        $paypalTokenId = $object->getData('paypal_token_id');
        if ($paypalTokenId !== null) {
            $object->setData('paypal_token_id_hash', hash('sha256', $paypalTokenId));
            $object->setData('paypal_token_id', Mage::helper('core')->encrypt($paypalTokenId));
        }
        return parent::_beforeSave($object);
    }

    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object): self
    {
        $encrypted = $object->getData('paypal_token_id');
        if ($encrypted !== null && $encrypted !== '') {
            $object->setData('paypal_token_id', Mage::helper('core')->decrypt($encrypted));
        }
        return parent::_afterSave($object);
    }

    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object): self
    {
        $encrypted = $object->getData('paypal_token_id');
        if ($encrypted !== null && $encrypted !== '') {
            $object->setData('paypal_token_id', Mage::helper('core')->decrypt($encrypted));
        }
        return parent::_afterLoad($object);
    }

    public static function hashTokenId(string $paypalTokenId): string
    {
        return hash('sha256', $paypalTokenId);
    }
}
