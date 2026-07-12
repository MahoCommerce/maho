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

        $this->pruneScheduledScans();

        if ($failed !== []) {
            $schedule->setMessages('Failed URLs: ' . implode(', ', $failed));
        }
    }

    /**
     * Keep only the newest N scheduled scans per URL. Covers every URL that
     * ever ran on a schedule (not just the currently configured list), so
     * removing a URL from the list does not leave its history behind forever.
     */
    protected function pruneScheduledScans(): void
    {
        $keep = Mage::helper('accessibilityscan')->getScheduledScanRetention();
        if ($keep === 0) {
            return;
        }

        $collection = Mage::getResourceModel('accessibilityscan/scan_collection')
            ->addFieldToFilter('triggered_by', Maho_AccessibilityScan_Model_Scan::TRIGGER_SCHEDULE)
            ->setOrder('created_at', 'DESC');

        $grouped = [];
        foreach ($collection as $scan) {
            $grouped[$scan->getUrl()][] = $scan;
        }

        foreach ($grouped as $scans) {
            foreach (array_slice($scans, $keep) as $scan) {
                try {
                    $scan->deleteWithScreenshots();
                } catch (Throwable $e) {
                    Mage::logException($e);
                }
            }
        }
    }
}
