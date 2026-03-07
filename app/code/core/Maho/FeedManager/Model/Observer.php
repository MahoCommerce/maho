<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Observer
{
    /**
     * Re-encrypt destination configs when encryption key is regenerated
     */
    public function encryptionKeyRegenerated(Maho\Event\Observer $observer): void
    {
        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $observer->getEvent()->getOutput();
        $encryptCallback = $observer->getEvent()->getEncryptCallback();
        $decryptCallback = $observer->getEvent()->getDecryptCallback();

        $output->write('Re-encrypting data on feedmanager_destination table... ');
        Mage::helper('core')->recryptTable(
            Mage::getSingleton('core/resource')->getTableName('feedmanager/destination'),
            'destination_id',
            ['config'],
            $encryptCallback,
            $decryptCallback,
        );
        $output->writeln('OK');
    }
}
