<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Observer
{
    #[Maho\Config\Observer('checkout_submit_all_after', id: 'paypal_save_order_after_submit')]
    public function saveOrderAfterSubmit(\Maho\DataObject $observer): void
    {
        $order = $observer->getData('order');
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }

        $method = $payment->getMethod();
        $mahoPpMethods = [
            Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_VAULT,
        ];

        if (!in_array($method, $mahoPpMethods)) {
            return;
        }

        $paypalOrderId = $payment->getAdditionalInformation('paypal_order_id');
        if ($paypalOrderId) {
            $payment->setData('paypal_order_id', $paypalOrderId);
            $payment->save();
        }
    }

    #[Maho\Config\Observer('encryption_key_regenerated', id: 'paypal_encryption_key_regenerated')]
    public function encryptionKeyRegenerated(Maho\Event\Observer $observer): void
    {
        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $observer->getEvent()->getOutput();
        $encryptCallback = $observer->getEvent()->getEncryptCallback();
        $decryptCallback = $observer->getEvent()->getDecryptCallback();

        $output->write('Re-encrypting data on paypal_vault_token table... ');
        $result = Mage::helper('core')->recryptTable(
            Mage::getSingleton('core/resource')->getTableName('paypal/vault_token'),
            'token_id',
            ['paypal_token_id'],
            $encryptCallback,
            $decryptCallback,
            output: $output,
        );
        $output->writeln($result ? 'OK' : '<comment>SKIPPED</comment>');
    }
}
