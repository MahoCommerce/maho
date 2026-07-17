<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

class Mage_Reports_Model_Observer
{
    /**
     * Refresh viewed report statistics for last day
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @return $this
     */
    #[Maho\Config\CronJob('aggregate_reports_report_product_viewed_data', configPath: 'reports/crontab/viewed_expr')]
    public function aggregateReportsReportProductViewedData($schedule)
    {
        Mage::app()->getLocale()->emulate(0);
        $currentDate = Mage::app()->getLocale()->utcToStore();
        $date = $currentDate->modify('-25 hours');
        Mage::getResourceModel('reports/report_product_viewed')->aggregate($date);
        Mage::app()->getLocale()->revert();
        return $this;
    }
}
