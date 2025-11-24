<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Log_Helper_Dashboard extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Log';

    public const CACHE_TAG = 'log_dashboard';
    public const CACHE_LIFETIME_REALTIME = 300;   // 5 minutes
    public const CACHE_LIFETIME_HOURLY = 3600;    // 1 hour

    /**
     * Generate cache key with store ID for multi-store support
     */
    protected function _getCacheKey(string $suffix): string
    {
        return 'log_dashboard_' . Mage::app()->getStore()->getId() . '_' . $suffix;
    }

    /**
     * Get online visitor count (real-time)
     */
    public function getOnlineCount(): int
    {
        return Mage::getModel('log/visitor_online')->prepare()->getCollection()->count();
    }

    /**
     * Get today's visitor count (cached 5 minutes)
     */
    public function getTodayCount(): int
    {
        $cacheKey = $this->_getCacheKey('today_count_' . date('Y-m-d'));
        $count = Mage::app()->getCache()->load($cacheKey);

        if ($count === false) {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from('log_visitor', ['count' => 'COUNT(*)'])
                ->where('DATE(first_visit_at) = ?', Mage_Core_Model_Locale::today());

            $count = $adapter->fetchOne($select);
            Mage::app()->getCache()->save(
                (string) $count,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_REALTIME,
            );
        }

        return (int) $count;
    }

    /**
     * Get this week's visitor count (cached 5 minutes)
     */
    public function getWeekCount(): int
    {
        $cacheKey = $this->_getCacheKey('week_count_' . date('Y-W'));
        $count = Mage::app()->getCache()->load($cacheKey);

        if ($count === false) {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from('log_visitor', ['count' => 'COUNT(*)'])
                ->where('first_visit_at >= ?', date('Y-m-d 00:00:00', strtotime('-7 days')));

            $count = $adapter->fetchOne($select);
            Mage::app()->getCache()->save(
                (string) $count,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_REALTIME,
            );
        }

        return (int) $count;
    }

    /**
     * Get visitor trend data for chart (uses pre-aggregated log_summary)
     *
     * @param int $days Number of days to retrieve
     * @return array ['labels' => [...], 'data' => [...]]
     */
    public function getVisitorTrends(int $days = 30): array
    {
        $cacheKey = $this->_getCacheKey('trends_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            // Get daily aggregated data from log_summary
            $select = $adapter->select()
                ->from('log_summary', ['add_date', 'visitor_count'])
                ->where('add_date >= ?', date('Y-m-d H:00:00', strtotime("-{$days} days")))
                ->order('add_date ASC');

            $rows = $adapter->fetchAll($select);

            // Group by day
            $dailyData = [];
            foreach ($rows as $row) {
                $day = date('Y-m-d', strtotime($row['add_date']));
                if (!isset($dailyData[$day])) {
                    $dailyData[$day] = 0;
                }
                $dailyData[$day] += (int) $row['visitor_count'];
            }

            // Fill in missing days with zeros
            $labels = [];
            $data = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('M j', strtotime($date));
                $data[] = $dailyData[$date] ?? 0;
            }

            $result = Mage::helper('core')->jsonEncode([
                'labels' => $labels,
                'data' => $data,
            ]);

            Mage::app()->getCache()->save(
                $result,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_REALTIME,
            );
        }

        return Mage::helper('core')->jsonDecode($result);
    }

    /**
     * Get top visited pages
     */
    public function getTopPages(int $days = 7, int $limit = 10): array
    {
        $cacheKey = $this->_getCacheKey('top_pages_' . $days . '_' . $limit);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $select = $adapter->select()
                ->from(['lu' => 'log_url'], ['views' => 'COUNT(*)'])
                ->join(
                    ['lui' => 'log_url_info'],
                    'lu.url_id = lui.url_id',
                    ['url'],
                )
                ->where('lu.visit_time >= ?', date('Y-m-d', strtotime("-{$days} days")))
                ->where('lui.url IS NOT NULL')
                ->where('lui.url != ?', '')
                ->group('lui.url')
                ->order('views DESC')
                ->limit($limit);

            $rows = $adapter->fetchAll($select);
            $result = Mage::helper('core')->jsonEncode($rows);

            Mage::app()->getCache()->save(
                $result,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_HOURLY,
            );
        }

        return Mage::helper('core')->jsonDecode($result);
    }

    /**
     * Get traffic sources breakdown
     */
    public function getTrafficSources(int $days = 7): array
    {
        $cacheKey = $this->_getCacheKey('sources_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $select = $adapter->select()
                ->from(['v' => 'log_visitor'], ['visitor_id'])
                ->join(
                    ['vi' => 'log_visitor_info'],
                    'v.visitor_id = vi.visitor_id',
                    ['http_referer'],
                )
                ->where('v.first_visit_at >= ?', date('Y-m-d', strtotime("-{$days} days")));

            $visitors = $adapter->fetchAll($select);

            $sources = [
                'direct' => 0,
                'organic' => 0,
                'social' => 0,
                'email' => 0,
                'referral' => 0,
            ];

            foreach ($visitors as $visitor) {
                $referer = $visitor['http_referer'];
                $type = $this->_classifyReferrer($referer);
                $sources[$type]++;
            }

            $result = Mage::helper('core')->jsonEncode($sources);

            Mage::app()->getCache()->save(
                $result,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_HOURLY,
            );
        }

        return Mage::helper('core')->jsonDecode($result);
    }

    /**
     * Get device and browser breakdown
     *
     * @return array ['devices' => [...], 'browsers' => [...]]
     */
    public function getDeviceBreakdown(int $days = 7): array
    {
        $cacheKey = $this->_getCacheKey('devices_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $select = $adapter->select()
                ->from(['v' => 'log_visitor'], ['visitor_id'])
                ->join(
                    ['vi' => 'log_visitor_info'],
                    'v.visitor_id = vi.visitor_id',
                    ['http_user_agent'],
                )
                ->where('v.first_visit_at >= ?', date('Y-m-d', strtotime("-{$days} days")));

            $visitors = $adapter->fetchAll($select);

            $devices = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
            $browsers = [];

            foreach ($visitors as $visitor) {
                $ua = $visitor['http_user_agent'];

                // Device detection
                if (preg_match('/mobile|android|iphone/i', $ua)) {
                    $devices['mobile']++;
                } elseif (preg_match('/tablet|ipad/i', $ua)) {
                    $devices['tablet']++;
                } else {
                    $devices['desktop']++;
                }

                // Browser detection
                $browser = $this->_detectBrowser($ua);
                $browsers[$browser] = ($browsers[$browser] ?? 0) + 1;
            }

            $result = Mage::helper('core')->jsonEncode([
                'devices' => $devices,
                'browsers' => $browsers,
            ]);

            Mage::app()->getCache()->save(
                $result,
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_LIFETIME_HOURLY,
            );
        }

        return Mage::helper('core')->jsonDecode($result);
    }

    /**
     * Classify referrer into category
     *
     * @param string $referer
     */
    protected function _classifyReferrer(?string $referer): string
    {
        if (empty($referer)) {
            return 'direct';
        }

        $host = parse_url($referer, PHP_URL_HOST);

        // Internal referrer (same domain) = direct traffic
        $storeUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $storeHost = parse_url($storeUrl, PHP_URL_HOST);
        if ($host === $storeHost) {
            return 'direct';
        }

        // Search engines
        if (preg_match('/(google|bing|yahoo|duckduckgo|baidu|yandex)/i', $host)) {
            return 'organic';
        }

        // Social media
        if (preg_match('/(facebook|twitter|instagram|linkedin|pinterest|tiktok|reddit)/i', $host)) {
            return 'social';
        }

        // Email
        if (preg_match('/(mail\.|webmail|outlook)/i', $host)) {
            return 'email';
        }

        return 'referral';
    }

    /**
     * Detect browser from user agent
     */
    protected function _detectBrowser(string $userAgent): string
    {
        if (preg_match('/Edg/i', $userAgent)) {
            return 'Edge';
        }
        if (preg_match('/Chrome/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/Safari/i', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/Firefox/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/Opera|OPR/i', $userAgent)) {
            return 'Opera';
        }
        return 'Other';
    }

    /**
     * Get database read adapter
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getReadAdapter()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_read');
    }

    /**
     * Clear dashboard cache
     *
     * @return $this
     */
    public function clearCache(): self
    {
        Mage::app()->getCache()->clean([self::CACHE_TAG]);
        return $this;
    }
}
