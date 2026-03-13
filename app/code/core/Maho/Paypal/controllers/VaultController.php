<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_VaultController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch(): static
    {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }

        return $this;
    }

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    public function deleteAction(): void
    {
        $tokenId = (int) $this->getRequest()->getParam('id');
        $customerId = (int) Mage::getSingleton('customer/session')->getCustomerId();

        try {
            /** @var Maho_Paypal_Model_Vault_Token $token */
            $token = Mage::getModel('maho_paypal/vault_token')->load($tokenId);

            if (!$token->getId() || (int) $token->getCustomerId() !== $customerId) {
                Mage::throwException('Invalid payment method.');
            }

            // Delete from PayPal
            try {
                /** @var Maho_Paypal_Model_Api_Client $client */
                $client = Mage::getModel('maho_paypal/api_client');
                $client->deletePaymentToken($token->getPaypalTokenId());
            } catch (\Throwable $e) {
                Mage::logException($e);
            }

            $token->delete();

            Mage::getSingleton('customer/session')->addSuccess(
                Mage::helper('maho_paypal')->__('Payment method has been deleted.'),
            );
        } catch (\Throwable $e) {
            Mage::getSingleton('customer/session')->addError(
                Mage::helper('maho_paypal')->__('Unable to delete payment method.'),
            );
            Mage::logException($e);
        }

        $this->_redirect('paypal/vault');
    }
}
