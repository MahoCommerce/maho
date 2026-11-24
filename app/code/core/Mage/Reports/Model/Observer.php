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
        $currentDate = Mage::app()->getLocale()->utcToStore();
        $date = $currentDate->modify('-25 hours');
        Mage::getResourceModel('reports/report_product_viewed')->aggregate($date);
        return $this;
    }

    /**
     * Count a storefront 404 in the 404 Errors report. Every 404 of a storefront page passes here once, also after
     * a forward to the no-route page.
     */
    #[Maho\Config\Observer('controller_front_send_response_before', area: 'frontend')]
    public function recordNotFound(\Maho\Event\Observer $_observer): void
    {
        $request = Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        if ($response->getHttpResponseCode() !== 404) {
            return;
        }
        Mage::getSingleton('reports/notFound')->recordRequest($request, $response);
    }

    /** Mark the day of a saved or deleted order, so the next report calculates its daily totals again. */
    #[Maho\Config\Observer('sales_order_save_after')]
    #[Maho\Config\Observer('sales_order_delete_after')]
    public function markOrderDay(\Maho\Event\Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof Mage_Sales_Model_Order && Mage_Reports_Model_Explorer_DailyTotals::minDays() > 0) {
            Mage::getSingleton('reports/explorer_dailyTotals')->markChanged($order->getCreatedAt());
        }
    }

    #[Maho\Config\CronJob('reports_update_daily_totals', schedule: '*/5 * * * *')]
    public function updateDailyTotals(): void
    {
        Mage::getSingleton('reports/explorer_dailyTotals')->update(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    #[Maho\Config\CronJob('reports_send_subscriptions', schedule: '5 * * * *')]
    public function sendSubscriptions(): void
    {
        Mage::getService('reports/subscription')->queueDue(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    #[Maho\Config\CronJob('report_not_found_clean', schedule: '15 * * * *')]
    public function cleanNotFound(): void
    {
        Mage::getResourceSingleton('reports/notFound')->clean(
            Mage::getStoreConfigAsInt(Mage_Reports_Model_NotFound::XML_PATH_DAYS_TO_KEEP),
            Mage::getStoreConfigAsInt(Mage_Reports_Model_NotFound::XML_PATH_MAX_ROWS),
        );
    }

    /** A save of the reports settings clears the Explorer results, because a setting changes what a report counts. */
    #[Maho\Config\Observer('admin_system_config_changed_section_reports')]
    public function cleanExplorerCache(): void
    {
        Mage::app()->cleanCache([Mage_Reports_Model_Explorer_Runner::CACHE_TAG]);
    }
}
