<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Usage extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/usage');
    }

    /**
     * Record a single AI call. Aggregates per (consumer, platform, model,
     * store, day) into one row whose counters are incremented in place.
     *
     * Portable across MySQL/Postgres/SQLite: avoids `INSERT ... ON DUPLICATE
     * KEY UPDATE` (MySQL-only). Uses SELECT-then-INSERT-or-UPDATE with a
     * single retry on the unique-constraint violation that another worker
     * may produce between SELECT and INSERT for the very first call of the
     * day on a new (consumer, platform, model, store) combo.
     */
    public function recordCall(
        string $consumer,
        string $platform,
        string $model,
        int $storeId,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $today = Mage::app()->getLocale()->formatDateForDb('now', withTime: false);

        $existing = $this->loadAggregateRow($consumer, $platform, $model, $storeId, $today);
        if ($existing !== null) {
            $this->incrementCounters($existing, $inputTokens, $outputTokens);
            return;
        }

        $row = Mage::getModel('ai/usage');
        $row->setData([
            'consumer'       => $consumer,
            'platform'       => $platform,
            'model'          => $model,
            'store_id'       => $storeId,
            'period_date'    => $today,
            'request_count'  => 1,
            'input_tokens'   => $inputTokens,
            'output_tokens'  => $outputTokens,
        ]);

        try {
            $row->save();
        } catch (\Throwable $e) {
            // Race: another worker inserted the unique row between our SELECT
            // and our INSERT. Retry the increment path once and swallow if it
            // still misses (counter loss is preferable to fataling the AI call).
            $existing = $this->loadAggregateRow($consumer, $platform, $model, $storeId, $today);
            if ($existing !== null) {
                $this->incrementCounters($existing, $inputTokens, $outputTokens);
                return;
            }
            Mage::logException($e);
        }
    }

    private function loadAggregateRow(
        string $consumer,
        string $platform,
        string $model,
        int $storeId,
        string $periodDate,
    ): ?self {
        $row = $this->getCollection()
            ->addFieldToFilter('consumer', $consumer)
            ->addFieldToFilter('platform', $platform)
            ->addFieldToFilter('model', $model)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('period_date', $periodDate)
            ->setPageSize(1)
            ->getFirstItem();
        // getFirstItem() is declared to return Maho\DataObject (the collection's
        // parent type). It actually returns whatever the collection's row class is,
        // so we narrow with instanceof to satisfy the self|null return.
        return ($row instanceof self && $row->getId()) ? $row : null;
    }

    private function incrementCounters(
        self $row,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $row->setRequestCount((int) $row->getRequestCount() + 1);
        $row->setInputTokens((int) $row->getInputTokens() + $inputTokens);
        $row->setOutputTokens((int) $row->getOutputTokens() + $outputTokens);
        $row->save();
    }

    /**
     * Add a batch of counts to a (consumer, platform, model, store, day) row.
     * Used by the nightly aggregator to roll up completed async tasks on top
     * of any synchronous counts already recorded for the same day.
     *
     * Cross-DB safe — uses SELECT-then-UPDATE-or-INSERT rather than a
     * MySQL-only "ON DUPLICATE KEY UPDATE … + VALUES(...)" expression.
     */
    public function addAggregate(
        string $consumer,
        string $platform,
        string $model,
        int $storeId,
        string $periodDate,
        int $requestCount,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $existing = $this->loadAggregateRow($consumer, $platform, $model, $storeId, $periodDate);
        if ($existing !== null) {
            $existing->setRequestCount((int) $existing->getRequestCount() + $requestCount);
            $existing->setInputTokens((int) $existing->getInputTokens() + $inputTokens);
            $existing->setOutputTokens((int) $existing->getOutputTokens() + $outputTokens);
            $existing->save();
            return;
        }

        $row = Mage::getModel('ai/usage');
        $row->setData([
            'consumer'      => $consumer,
            'platform'      => $platform,
            'model'         => $model,
            'store_id'      => $storeId,
            'period_date'   => $periodDate,
            'request_count' => $requestCount,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);

        try {
            $row->save();
        } catch (\Throwable $e) {
            // Race with a concurrent sync recordCall() between our SELECT and
            // INSERT — fall back to the increment path.
            $existing = $this->loadAggregateRow($consumer, $platform, $model, $storeId, $periodDate);
            if ($existing !== null) {
                $existing->setRequestCount((int) $existing->getRequestCount() + $requestCount);
                $existing->setInputTokens((int) $existing->getInputTokens() + $inputTokens);
                $existing->setOutputTokens((int) $existing->getOutputTokens() + $outputTokens);
                $existing->save();
                return;
            }
            Mage::logException($e);
        }
    }
}
