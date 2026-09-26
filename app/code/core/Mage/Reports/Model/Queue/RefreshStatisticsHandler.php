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
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Reports_Model_Queue_RefreshStatistics $message): void
    {
        /** @var Mage_Reports_Model_Statistics $statistics */
        $statistics = Mage::getModel('reports/statistics');
        if (!$statistics->isKnownCode($message->code)) {
            return;
        }
        $statistics->refreshLifetime([$message->code]);
    }
}
