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

        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $output->write('Re-encrypting data on feedmanager_destination table... ');

        $table = Mage::getSingleton('core/resource')->getTableName('feedmanager/destination');

        $select = $readConnection->select()
            ->from($table, ['destination_id', 'config'])
            ->where('config IS NOT NULL')
            ->where('config != ?', '');

        $destinations = $readConnection->fetchAll($select);

        foreach ($destinations as $row) {
            $decrypted = $decryptCallback($row['config']);
            if ($decrypted !== '') {
                $writeConnection->update(
                    $table,
                    ['config' => $encryptCallback($decrypted)],
                    ['destination_id = ?' => $row['destination_id']],
                );
            }
        }

        $output->writeln('OK');
    }
}
