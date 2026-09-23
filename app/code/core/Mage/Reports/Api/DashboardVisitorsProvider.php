<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class DashboardVisitorsProvider extends ReportProviderBase
{
    public const SECTIONS = [
        'summary', 'trend', 'devices', 'engagement', 'entryPages', 'exitPages', 'languages', 'topPages', 'trafficSources',
    ];

    /**
     * The trend has the visitors of each of this number of days, the same as the admin chart.
     */
    public const TREND_DAYS = 30;

    public const MAX_DAYS = 90;

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        if ($query->scoped && count($query->storeIds) !== 1) {
            throw new BadRequestHttpException('The visitor data can be filtered by one store view only. Send storeId.');
        }
        $storeId = $query->scoped ? $query->storeIds[0] : \Mage_Core_Model_App::ADMIN_STORE_ID;
        $days = $query->readLimit($filters, 7, self::MAX_DAYS, 'days');
        $sections = $this->readSections($filters);

        $document = [
            'enabled' => (bool) \Mage::helper('log')->isVisitorLogEnabled(),
            'days' => $days,
            'scope' => $query->scopeArray(),
        ];
        if (!$document['enabled']) {
            return $document;
        }

        // The helper filters by the store parameter of the request, or else by the current store
        $request = \Mage::app()->getRequest();
        $storeParam = $request->getParam('store');
        $request->setParam('store', $storeId);
        try {
            $document += \Mage::app()->withStore($storeId, fn(): array => $this->sections($sections, $days));
        } finally {
            $request->setParam('store', $storeParam);
        }
        return $document;
    }

    /**
     * @param list<string> $sections
     * @return array<string, mixed>
     */
    private function sections(array $sections, int $days): array
    {
        /** @var \Mage_Log_Helper_Dashboard $helper */
        $helper = \Mage::helper('log/dashboard');
        $builders = [
            'summary' => function () use ($helper, $days): array {
                $sessions = $helper->getSessionMetrics($days);
                return [
                    'online' => $helper->getOnlineCount(),
                    'today' => $helper->getTodayCount(),
                    'lastSevenDays' => $helper->getWeekCount(),
                    'sessions' => (int) ($sessions['total_sessions'] ?? 0),
                    'averageDuration' => (int) ($sessions['avg_duration'] ?? 0),
                    'averagePages' => (float) ($sessions['avg_pages'] ?? 0),
                    'bounceRate' => (float) ($sessions['bounce_rate'] ?? 0),
                ];
            },
            'trend' => function () use ($helper): array {
                $trend = $helper->getVisitorTrends(self::TREND_DAYS);
                $points = [];
                foreach (array_values($trend['data'] ?? []) as $index => $visitors) {
                    $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . (self::TREND_DAYS - 1 - $index) . ' days');
                    $points[] = ['date' => $date->format('Y-m-d'), 'visitors' => (int) $visitors];
                }
                return $points;
            },
            'devices' => function () use ($helper, $days): array {
                $breakdown = $helper->getDeviceBreakdown($days);
                return [
                    'types' => array_map(intval(...), (array) ($breakdown['devices'] ?? [])),
                    'browsers' => self::countList((array) ($breakdown['browsers'] ?? []), 'name', 'visitors'),
                ];
            },
            'engagement' => function () use ($helper, $days): array {
                $conversion = $helper->getCustomerConversion($days);
                $newReturning = $helper->getNewVsReturning($days);
                return [
                    'visitors' => (int) ($conversion['visitors'] ?? 0),
                    'loggedIn' => (int) ($conversion['customers'] ?? 0),
                    'loginRate' => (float) ($conversion['conversion_rate'] ?? 0),
                    'new' => (int) ($newReturning['new'] ?? 0),
                    'returning' => (int) ($newReturning['returning'] ?? 0),
                ];
            },
            'entryPages' => fn(): array => self::pageList($helper->getEntryPages($days, 10), 'visits'),
            'exitPages' => fn(): array => self::pageList($helper->getExitPages($days, 10), 'exits'),
            'languages' => function () use ($helper, $days): array {
                $breakdown = $helper->getLanguageBreakdown($days, 10);
                $rows = [];
                foreach ((array) ($breakdown['languages'] ?? []) as $code => $visitors) {
                    $rows[] = ['code' => (string) $code, 'name' => $helper->getLanguageName((string) $code), 'visitors' => (int) $visitors];
                }
                return ['total' => (int) ($breakdown['total'] ?? 0), 'languages' => $rows];
            },
            'topPages' => fn(): array => self::pageList($helper->getTopPages($days, 20), 'views'),
            'trafficSources' => fn(): array => self::countList($helper->getTrafficSources($days, 10), 'source', 'visitors'),
        ];

        $document = [];
        foreach ($sections as $section) {
            $document[$section] = $builders[$section]();
        }
        return $document;
    }

    /**
     * @param array<array-key, mixed> $counts name => count
     * @return list<array<string, string|int>>
     */
    private static function countList(array $counts, string $nameKey, string $countKey): array
    {
        arsort($counts);
        $rows = [];
        foreach ($counts as $name => $count) {
            $rows[] = [$nameKey => (string) $name, $countKey => (int) $count];
        }
        return $rows;
    }

    /**
     * @param array<array-key, mixed> $rows rows with url and $countKey
     * @return list<array<string, string|int>>
     */
    private static function pageList(array $rows, string $countKey): array
    {
        $pages = [];
        foreach ($rows as $row) {
            $pages[] = ['url' => (string) ($row['url'] ?? ''), $countKey => (int) ($row[$countKey] ?? 0)];
        }
        return $pages;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<string>
     */
    private function readSections(array $filters): array
    {
        $value = $this->stringFilter($filters, 'sections');
        if ($value === null) {
            return self::SECTIONS;
        }
        $requested = [];
        foreach (explode(',', $value) as $section) {
            $section = trim($section);
            if (!in_array($section, self::SECTIONS, true)) {
                throw new BadRequestHttpException('sections must be a comma-separated list of: ' . implode(', ', self::SECTIONS));
            }
            $requested[$section] = true;
        }
        return array_values(array_filter(self::SECTIONS, static fn(string $section): bool => isset($requested[$section])));
    }
}
