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
        $helper = Mage::helper('customersegmentation');
        $refreshFrequency = $helper->getRefreshFrequency();

        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addIsActiveFilter()
            ->addFieldToFilter('refresh_mode', Maho_CustomerSegmentation_Model_Segment::MODE_AUTO)
            ->addFieldToFilter(
                ['last_refresh_at', 'last_refresh_at'],
                [
                    ['lt' => Mage::app()->getLocale()->utcDate(null, "-{$refreshFrequency} hours", false, Mage_Core_Model_Locale::DATETIME_FORMAT)],
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
                ), Mage::LOG_ERROR, 'customer_segmentation.log');

                Mage::logException($e);
            }
        }

        Mage::log('Segment refresh completed.', null, 'customer_segmentation.log');
    }

}
