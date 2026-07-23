<?php

/**
 * Scheduled accessibility scans: runs the configured URL list through the
 * scanner and prunes old scheduled results.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Cron
{
    #[Maho\Config\CronJob('accessibilityscan_scheduled', configPath: 'accessibilityscan/scheduled/schedule')]
    public function runScheduledScans(Mage_Cron_Model_Schedule $schedule): void
    {
        $helper = Mage::helper('accessibilityscan');
        if (!$helper->isScheduledScanEnabled()) {
            return;
        }

        $urls = $helper->getScheduledScanUrls();
        if ($urls === []) {
            Mage::log('AccessibilityScan: scheduled scans are enabled but no URLs are configured', Mage::LOG_WARNING);
            return;
        }

        $level = $helper->getDefaultWcagLevel();
        $failed = [];
        foreach ($urls as $url) {
            // Re-validate on every run: store base URLs may have changed since the list was saved
            $storeId = $helper->resolveScanUrlStoreId($url);
            if ($storeId === null) {
                Mage::log("AccessibilityScan: skipping scheduled scan of $url: the URL no longer matches any store base URL", Mage::LOG_WARNING);
                $failed[] = $url;
                continue;
            }

            try {
                $scan = Mage::getModel('accessibilityscan/scan');
                $scan->setUrl($url)
                    ->setStoreId($storeId)
                    ->setWcagLevel($level)
                    ->setTriggeredBy(Maho_AccessibilityScan_Model_Scan::TRIGGER_SCHEDULE)
                    ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
                    ->save();

                Mage::getModel('accessibilityscan/runner')->run($scan);
                if ($scan->isFailed()) {
                    $failed[] = $url;
                }
            } catch (Throwable $e) {
                Mage::logException($e);
                $failed[] = $url;
            }
        }

        if ($failed !== []) {
            $schedule->setMessages('Failed URLs: ' . implode(', ', $failed));
        }
    }

    /**
     * Delete scans older than the configured age, regardless of how they
     * were triggered. Runs on its own schedule so cleanup works even when
     * scheduled scans are disabled.
     */
    #[Maho\Config\CronJob('accessibilityscan_cleanup', schedule: '30 3 * * *')]
    public function cleanupOldScans(): void
    {
        $days = Mage::helper('accessibilityscan')->getCleanupDays();
        if ($days === 0) {
            return;
        }

        $cutoff = Mage::app()->getLocale()->formatDateForDb("-{$days} days");
        $collection = Mage::getResourceModel('accessibilityscan/scan_collection')
            ->addFieldToFilter('created_at', ['lt' => $cutoff]);

        foreach ($collection as $scan) {
            try {
                $scan->deleteWithScreenshots();
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
    }
}
