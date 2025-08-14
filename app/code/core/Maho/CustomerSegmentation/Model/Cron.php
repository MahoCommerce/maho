<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Cron
{
    public function refreshSegments(): void
    {
        if (!Mage::helper('customersegmentation')->isEnabled()) {
            return;
        }

        $helper = Mage::helper('customersegmentation');
        $refreshFrequency = $helper->getRefreshFrequency();

        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addIsActiveFilter()
            ->addFieldToFilter('refresh_mode', Maho_CustomerSegmentation_Model_Segment::MODE_AUTO)
            ->addFieldToFilter(
                ['last_refresh_at', 'last_refresh_at'],
                [
                    ['lt' => date('Y-m-d H:i:s', strtotime("-{$refreshFrequency} hours"))],
                    ['null' => true],
                ],
            );

        Mage::log(
            sprintf('Starting segment refresh. Found %d segments to refresh.', $collection->getSize()),
            null,
            'customer_segmentation.log',
        );

        foreach ($collection as $segment) {
            try {
                $startTime = microtime(true);
                $startMemory = memory_get_usage();

                Mage::log(
                    sprintf('Refreshing segment: %s (ID: %d)', $segment->getName(), $segment->getId()),
                    null,
                    'customer_segmentation.log',
                );

                $segment->refreshCustomers();

                $executionTime = microtime(true) - $startTime;
                $memoryUsed = memory_get_usage() - $startMemory;

                Mage::log(sprintf(
                    'Segment %d refreshed successfully. Time: %.2fs, Memory: %.2fMB, Customers: %d',
                    $segment->getId(),
                    $executionTime,
                    $memoryUsed / 1024 / 1024,
                    $segment->getMatchedCustomersCount(),
                ), null, 'customer_segmentation.log');

            } catch (Exception $e) {
                Mage::log(sprintf(
                    'Error refreshing segment %d: %s',
                    $segment->getId(),
                    $e->getMessage(),
                ), Zend_Log::ERR, 'customer_segmentation.log');

                Mage::logException($e);
            }
        }

        Mage::log('Segment refresh completed.', null, 'customer_segmentation.log');
    }

    /**
     * Clean up old event records
     */
    public function cleanupEvents(): void
    {
        if (!Mage::helper('customersegmentation')->isEnabled()) {
            return;
        }

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $table = Mage::getSingleton('core/resource')->getTableName('customersegmentation/segment_event');

            // Remove processed events older than 30 days
            $deleteCount = $connection->delete($table, [
                'processed = ?' => 1,
                'created_at < ?' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            Mage::log(sprintf('Cleaned up %d old event records.', $deleteCount), null, 'customer_segmentation.log');

            // Also cleanup guest records older than configured period (default 180 days)
            $guestTable = Mage::getSingleton('core/resource')->getTableName('customersegmentation/segment_guest');
            $guestRetentionDays = 180; // Could be made configurable

            $guestDeleteCount = $connection->delete($guestTable, [
                'last_visit_at < ?' => date('Y-m-d H:i:s', strtotime("-{$guestRetentionDays} days")),
            ]);

            if ($guestDeleteCount > 0) {
                Mage::log(sprintf('Cleaned up %d old guest records.', $guestDeleteCount), null, 'customer_segmentation.log');
            }

        } catch (Exception $e) {
            Mage::log(
                sprintf('Error during event cleanup: %s', $e->getMessage()),
                Zend_Log::ERR,
                'customer_segmentation.log',
            );
            Mage::logException($e);
        }
    }
}
