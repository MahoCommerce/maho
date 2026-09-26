<?php

/**
 * The aggregated reports whose statistics an admin refreshes, and the refresh of their tables.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Statistics
{
    /**
     * Code of each report with its aggregation resource model, flag code, label and description.
     * The label and the description are English source strings of the module in "module".
     */
    public const REPORTS = [
        'sales' => [
            'resource' => 'sales/report_order',
            'flag' => Mage_Reports_Model_Flag::REPORT_ORDER_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Orders',
            'description' => 'Total Ordered Report',
        ],
        'tax' => [
            'resource' => 'tax/report_tax',
            'flag' => Mage_Reports_Model_Flag::REPORT_TAX_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Tax',
            'description' => 'Order Taxes Report Grouped by Tax Rates',
        ],
        'shipping' => [
            'resource' => 'sales/report_shipping',
            'flag' => Mage_Reports_Model_Flag::REPORT_SHIPPING_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Shipping',
            'description' => 'Total Shipped Report',
        ],
        'invoiced' => [
            'resource' => 'sales/report_invoiced',
            'flag' => Mage_Reports_Model_Flag::REPORT_INVOICE_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Total Invoiced',
            'description' => 'Total Invoiced VS Paid Report',
        ],
        'refunded' => [
            'resource' => 'sales/report_refunded',
            'flag' => Mage_Reports_Model_Flag::REPORT_REFUNDED_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Total Refunded',
            'description' => 'Total Refunded Report',
        ],
        'coupons' => [
            'resource' => 'salesrule/report_rule',
            'flag' => Mage_Reports_Model_Flag::REPORT_COUPONS_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Coupons',
            'description' => 'Promotion Coupons Usage Report',
        ],
        'bestsellers' => [
            'resource' => 'sales/report_bestsellers',
            'flag' => Mage_Reports_Model_Flag::REPORT_BESTSELLERS_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Bestsellers',
            'description' => 'Products Bestsellers Report',
        ],
        'viewed' => [
            'resource' => 'reports/report_product_viewed',
            'flag' => Mage_Reports_Model_Flag::REPORT_PRODUCT_VIEWED_FLAG_CODE,
            'module' => 'sales',
            'label' => 'Most Viewed',
            'description' => 'Most Viewed Products Report',
        ],
    ];

    /**
     * The recent refresh aggregates the orders of this number of hours before now.
     */
    public const RECENT_HOURS = 25;

    /**
     * @return list<string>
     */
    public function getCodes(): array
    {
        return array_keys(self::REPORTS);
    }

    public function isKnownCode(string $code): bool
    {
        return isset(self::REPORTS[$code]);
    }

    public function getResourceModelName(string $code): string
    {
        return self::REPORTS[$code]['resource'] ?? throw new InvalidArgumentException("Unknown report code: {$code}");
    }

    public function getLabel(string $code): string
    {
        $report = self::REPORTS[$code] ?? throw new InvalidArgumentException("Unknown report code: {$code}");
        return Mage::helper($report['module'])->__($report['label']);
    }

    public function getDescription(string $code): string
    {
        $report = self::REPORTS[$code] ?? throw new InvalidArgumentException("Unknown report code: {$code}");
        return Mage::helper($report['module'])->__($report['description']);
    }

    /**
     * Return the time of the last refresh of the report $code in UTC ('Y-m-d H:i:s'), or null when it never ran.
     */
    public function getUpdatedAt(string $code): ?string
    {
        $report = self::REPORTS[$code] ?? throw new InvalidArgumentException("Unknown report code: {$code}");
        $flag = Mage::getModel('reports/flag')->setReportFlagCode($report['flag'])->loadSelf();
        $lastUpdate = $flag->getLastUpdate();
        return is_string($lastUpdate) && $lastUpdate !== '' ? $lastUpdate : null;
    }

    /**
     * Aggregate the orders of the last RECENT_HOURS hours again for each report in $codes.
     *
     * @param list<string> $codes
     */
    public function refreshRecent(array $codes): void
    {
        $this->withAdminStore(function () use ($codes): void {
            $from = Mage::app()->getLocale()->utcToStore()->modify('-' . self::RECENT_HOURS . ' hours');
            foreach ($codes as $code) {
                $this->aggregate($code, $from);
            }
        });
    }

    /**
     * Empty the tables of each report in $codes and aggregate all orders again.
     *
     * @param list<string> $codes
     */
    public function refreshLifetime(array $codes): void
    {
        $this->withAdminStore(function () use ($codes): void {
            foreach ($codes as $code) {
                $this->aggregate($code);
            }
        });
    }

    /**
     * Aggregate the report $code from the store-local date $from, or all orders when $from is null.
     */
    public function aggregate(string $code, ?\DateTimeInterface $from = null): void
    {
        $resource = Mage::getResourceModel($this->getResourceModelName($code));
        if (!is_object($resource) || !method_exists($resource, 'aggregate')) {
            throw new RuntimeException("The resource model of the report {$code} cannot aggregate");
        }
        $resource->aggregate($from);
    }

    /**
     * Run $callback with the admin store as the current store.
     *
     * The aggregation converts the order dates to the time zone of the current store, and the
     * report tables must use the time zone of the default scope. The cron and the admin run in the
     * admin store, but an API request or a queue worker can run in a store view with its own time zone.
     *
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    public function withAdminStore(\Closure $callback): mixed
    {
        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, $callback);
    }
}
