<?php

/**
 * Empties the tables of one report and aggregates all orders again.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Queue_RefreshStatisticsHandler
{
    /**
     * @throws Throwable when the report could not be aggregated, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Reports_Model_Queue_RefreshStatistics $message): void
    {
        /** @var Mage_Reports_Model_Statistics $statistics */
        $statistics = Mage::getModel('reports/statistics');
        if (!$statistics->isKnownCode($message->code)) {
            return;
        }
        if ($message->adminUserId === null) {
            $statistics->refreshLifetime([$message->code]);
            return;
        }

        $helper = Mage::helper('reports');
        $label = $statistics->getLabel($message->code);
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($statistics, $message, $helper, $label): string {
                $statistics->refreshLifetime([$message->code]);
                return $helper->__('The lifetime statistics of the report "%s" are up to date.', $label);
            },
            $helper->__('The lifetime statistics have been updated'),
            $helper->__('The lifetime statistics of the report "%s" could not be updated', $label),
            'adminhtml/report_statistics/index',
            $message->adminUserId,
            'report/statistics',
        );
    }
}
