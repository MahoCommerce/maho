<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

class Mage_Reports_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const REPORT_PERIOD_TYPE_DAY    = 'day';
    public const REPORT_PERIOD_TYPE_MONTH  = 'month';
    public const REPORT_PERIOD_TYPE_YEAR   = 'year';

    public const XML_PATH_REPORTS_ENABLED  = 'reports/general/enabled';

    #[\Override]
    protected $_moduleName = 'Mage_Reports';

    /**
     * Return reports flag enabled.
     *
     * @return bool
     */

    public function isReportsEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_REPORTS_ENABLED);
    }

    public function isRecentlyViewedEnabled(): bool
    {
        return Mage::getStoreConfigFlag('catalog/recently_products/enabled_recently_viewed');
    }

    public function isProductCompareEnabled(): bool
    {
        return Mage::getStoreConfigFlag('catalog/recently_products/enabled_product_compare');
    }

    /**
     * Retrieve array of intervals
     *
     * @param string $from
     * @param string $to
     * @param self::REPORT_PERIOD_TYPE_* $period
     * @return array
     */
    public function getIntervals($from, $to, $period = self::REPORT_PERIOD_TYPE_DAY)
    {
        $intervals = [];
        $dateStart = null;

        if (!$from && !$to) {
            return $intervals;
        }

        $start = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $from) ?: new DateTime($from);

        if ($period == self::REPORT_PERIOD_TYPE_DAY) {
            $dateStart = $start;
        }

        if ($period == self::REPORT_PERIOD_TYPE_MONTH) {
            $dateStart = new DateTime($start->format('Y-m'));
        }

        if ($period == self::REPORT_PERIOD_TYPE_YEAR) {
            $dateStart = new DateTime($start->format('Y-01-01'));
        }

        if (!$period || !$dateStart) {
            return $intervals;
        }

        $dateEnd = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $to) ?: new DateTime($to);

        while ($dateStart <= $dateEnd) {
            $time = '';
            switch ($period) {
                case self::REPORT_PERIOD_TYPE_DAY:
                    $time = $dateStart->format(Mage_Core_Model_Locale::DATE_FORMAT);
                    $dateStart->modify('+1 day');
                    break;
                case self::REPORT_PERIOD_TYPE_MONTH:
                    $time = $dateStart->format('Y-m');
                    $dateStart->modify('+1 month');
                    break;
                case self::REPORT_PERIOD_TYPE_YEAR:
                    $time = $dateStart->format('Y');
                    $dateStart->modify('+1 year');
                    break;
            }
            $intervals[] = $time;
        }
        return  $intervals;
    }

    /**
     * @param \Maho\Data\Collection $collection
     * @param string $from
     * @param string $to
     * @param string $periodType
     */
    public function prepareIntervalsCollection($collection, $from, $to, $periodType = self::REPORT_PERIOD_TYPE_DAY): void
    {
        $intervals = $this->getIntervals($from, $to, $periodType);

        foreach ($intervals as $interval) {
            $item = Mage::getModel('adminhtml/report_item');
            $item->setPeriod($interval);
            $item->setIsEmpty();
            $collection->addItem($item);
        }
    }

    /**
     * The shape of a saved report for the Explorer page.
     *
     * @return array{id: int, name: string, shared: bool, mine: bool, request: array<string, mixed>, updatedAt: ?string}
     */
    public function savedReportToArray(Mage_Reports_Model_SavedReport $report, ?int $adminUserId): array
    {
        return [
            'id' => (int) $report->getId(),
            'name' => (string) $report->getName(),
            'shared' => (bool) $report->getIsShared(),
            'mine' => $adminUserId !== null && $report->getAdminUserId() === $adminUserId,
            'request' => $report->getRequestData(),
            'updatedAt' => $report->getUpdatedAt(),
        ];
    }

    /**
     * The saved reports that an administrator sees on the Explorer page.
     *
     * @return list<array{id: int, name: string, shared: bool, mine: bool, request: array<string, mixed>, updatedAt: ?string}>
     */
    public function savedReportsFor(?int $adminUserId, Mage_Reports_Model_Explorer_Access $access): array
    {
        return array_map(
            fn(Mage_Reports_Model_SavedReport $report): array => $this->savedReportToArray($report, $adminUserId),
            Mage::getService('reports/savedReport')->listFor($adminUserId, $access),
        );
    }
}
