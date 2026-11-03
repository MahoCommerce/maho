<?php

/**
 * Refresh the statistics of the aggregated reports, for the admin and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class Mage_Reports_Service_Statistics
{
    public const XML_PATH_RATE_LIMIT = 'system/rate_limit/reports_refresh';

    /**
     * The window of the rate limit XML_PATH_RATE_LIMIT, in seconds.
     */
    public const RATE_LIMIT_WINDOW = 60;

    /**
     * Aggregate the orders of the last Mage_Reports_Model_Statistics::RECENT_HOURS hours again for each report in $codes.
     *
     * @param list<mixed> $codes the report codes, or an empty list for every report
     * @param string $caller the admin user or the API client that asks, for the rate limit
     * @return list<string> the codes of the refreshed reports
     * @throws Mage_Core_Exception_Input when a code is not a report code
     * @throws Mage_Core_Exception_TooManyRequests when $caller is over its limit
     */
    public function refreshRecent(array $codes, string $caller): array
    {
        $codes = $this->readCodes($codes);
        $this->checkRateLimit($caller);
        $this->statistics()->refreshRecent($codes);
        return $codes;
    }

    /**
     * Queue one message for each report in $codes, which empties the tables of the report and aggregates all
     * orders again. A report that is in the queue already is not queued again.
     *
     * @param list<mixed> $codes the report codes, or an empty list for every report
     * @param string $caller the admin user or the API client that asks, for the rate limit
     * @param int|null $notifyAdminUserId the admin user who gets a message in the inbox when each report is done
     * @return array<string, bool> for each code, true when this call queued it and false when it was in the queue already
     * @throws Mage_Core_Exception_Input when a code is not a report code
     * @throws Mage_Core_Exception_TooManyRequests when $caller is over its limit
     */
    public function refreshLifetime(array $codes, string $caller, ?int $notifyAdminUserId = null): array
    {
        $codes = $this->readCodes($codes);
        $this->checkRateLimit($caller);

        $queued = [];
        foreach ($codes as $code) {
            $message = new Mage_Reports_Model_Queue_RefreshStatistics($code, $notifyAdminUserId);
            $envelope = \Maho\Queue\QueueManager::dispatch(
                $message,
                queue: Mage_Reports_Model_Queue_RefreshStatistics::QUEUE_NAME,
                dedupeKey: $message->getDedupeKey(),
            );
            $queued[$code] = $envelope->last(TransportMessageIdStamp::class) !== null;
        }
        return $queued;
    }

    /**
     * @param list<mixed> $codes
     * @return list<string> the codes in the order of the report map, each one once
     * @throws Mage_Core_Exception_Input
     */
    private function readCodes(array $codes): array
    {
        $statistics = $this->statistics();
        if ($codes === []) {
            return $statistics->getCodes();
        }
        foreach ($codes as $code) {
            if (!is_string($code) || !$statistics->isKnownCode($code)) {
                throw new Mage_Core_Exception_Input()->addError(
                    'reports',
                    Mage::helper('reports')->__('reports must be a list of: %s', implode(', ', $statistics->getCodes())),
                );
            }
        }
        return array_values(array_intersect($statistics->getCodes(), $codes));
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function checkRateLimit(string $caller): void
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT);
        if (!Mage::helper('core')->rateLimiterBy('reports_refresh', 'reports_refresh:' . $caller, $limit, self::RATE_LIMIT_WINDOW)->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(
                Mage::helper('reports')->__('Too many requests. Please try again later.'),
                self::RATE_LIMIT_WINDOW,
            );
        }
    }

    private function statistics(): Mage_Reports_Model_Statistics
    {
        return Mage::getModel('reports/statistics');
    }
}
