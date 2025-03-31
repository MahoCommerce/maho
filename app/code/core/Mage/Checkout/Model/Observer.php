<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Checkout_Model_Observer
{
    public function unsetAll()
    {
        Mage::getSingleton('checkout/session')->unsetAll();
    }

    public function loadCustomerQuote()
    {
        try {
            Mage::getSingleton('checkout/session')->loadCustomerQuote();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addException(
                $e,
                Mage::helper('checkout')->__('Load customer quote error'),
            );
        }
    }

    public function salesQuoteSaveAfter(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        /** @var Mage_Sales_Model_Quote $quote */
        if ($quote->getIsCheckoutCart()) {
            Mage::getSingleton('checkout/session')->getQuoteId($quote->getId());
        }
    }

    public function encryptionKeyRegenerated(Varien_Event_Observer $observer): void
    {
        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $observer->getEvent()->getOutput();
        $encryptCallback = $observer->getEvent()->getEncryptCallback();
        $decryptCallback = $observer->getEvent()->getDecryptCallback();
        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $output->write('Re-encrypting data on sales_flat_quote table... ');

        $table = Mage::getSingleton('core/resource')->getTableName('sales_flat_quote');
        $select = $readConnection->select()
            ->from($table)
            ->where('password_hash IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);

        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['password_hash' => $encryptCallback($decryptCallback($encryptedDataRow['password_hash']))],
                ['entity_id = ?' => $encryptedDataRow['entity_id']],
            );
        }

        $output->writeln('OK');
    }
}
