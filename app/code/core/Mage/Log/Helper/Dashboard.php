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
        $storeId = $this->_getStoreIdForFilter();
        return 'log_dashboard_' . $storeId . '_' . $suffix;
    }

    /**
     * Get store ID for filtering, respecting admin store switcher
     */
    protected function _getStoreIdForFilter(): int
    {
        // In admin, check for store switcher parameter
        $storeId = (int) Mage::app()->getRequest()->getParam('store', 0);
        if ($storeId > 0) {
            return $storeId;
        }

        // Fall back to current store (0 for admin = all stores)
        return (int) Mage::app()->getStore()->getId();
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
                ->from($this->_getTable('log_visitor'), ['count' => 'COUNT(*)'])
                ->where('DATE(first_visit_at) = ?', Mage_Core_Model_Locale::today());

            $this->_addStoreFilter($select);

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
                ->from($this->_getTable('log_visitor'), ['count' => 'COUNT(*)'])
                ->where('first_visit_at >= ?', date('Y-m-d 00:00:00', strtotime('-7 days')));

            $this->_addStoreFilter($select);

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
                ->from($this->_getTable('log_summary'), ['add_date', 'visitor_count'])
                ->where('add_date >= ?', date('Y-m-d H:00:00', strtotime("-{$days} days")))
                ->order('add_date ASC');

            $this->_addStoreFilter($select);

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
                ->from(['lu' => $this->_getTable('log_url')], ['views' => 'COUNT(*)'])
                ->join(
                    ['lui' => $this->_getTable('log_url_info')],
                    'lu.url_id = lui.url_id',
                    ['url'],
                )
                ->join(
                    ['lv' => $this->_getTable('log_visitor')],
                    'lu.visitor_id = lv.visitor_id',
                    [],
                )
                ->where('lu.visit_time >= ?', date('Y-m-d', strtotime("-{$days} days")))
                ->where('lui.url IS NOT NULL')
                ->where('lui.url != ?', '');

            $this->_addStoreFilter($select, 'lv');

            $select->group('lui.url')
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
                ->from(['v' => $this->_getTable('log_visitor')], ['visitor_id'])
                ->join(
                    ['vi' => $this->_getTable('log_visitor_info')],
                    'v.visitor_id = vi.visitor_id',
                    ['http_referer'],
                )
                ->where('v.first_visit_at >= ?', date('Y-m-d', strtotime("-{$days} days")))
                ->limit(10000);

            $this->_addStoreFilter($select, 'v');

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
                ->from(['v' => $this->_getTable('log_visitor')], ['visitor_id'])
                ->join(
                    ['vi' => $this->_getTable('log_visitor_info')],
                    'v.visitor_id = vi.visitor_id',
                    ['http_user_agent'],
                )
                ->where('v.first_visit_at >= ?', date('Y-m-d', strtotime("-{$days} days")))
                ->limit(10000);

            $this->_addStoreFilter($select, 'v');

            $visitors = $adapter->fetchAll($select);

            $devices = ['mobile' => 0, 'desktop' => 0];
            $browsers = [];

            foreach ($visitors as $visitor) {
                $ua = $visitor['http_user_agent'] ?? '';
                $parsed = \donatj\UserAgent\parse_user_agent($ua);

                // Device detection based on platform
                $platform = $parsed[\donatj\UserAgent\PLATFORM] ?? '';
                $device = $this->_classifyDevice($platform);
                $devices[$device]++;

                // Browser detection
                $browser = $parsed[\donatj\UserAgent\BROWSER] ?? 'Other';
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
     * Classify device type based on platform from user agent parser
     *
     * @see \donatj\UserAgent\Platforms for available platform constants
     */
    protected function _classifyDevice(string $platform): string
    {
        // Mobile devices (includes tablets - in 2025 the distinction is less relevant)
        $mobile = [
            \donatj\UserAgent\Platforms::IPHONE,
            \donatj\UserAgent\Platforms::IPOD,
            \donatj\UserAgent\Platforms::IPAD,
            \donatj\UserAgent\Platforms::ANDROID,
            \donatj\UserAgent\Platforms::WINDOWS_PHONE,
            \donatj\UserAgent\Platforms::BLACKBERRY,
            \donatj\UserAgent\Platforms::KINDLE,
            \donatj\UserAgent\Platforms::KINDLE_FIRE,
            \donatj\UserAgent\Platforms::PLAYBOOK,
            \donatj\UserAgent\Platforms::SYMBIAN,
            \donatj\UserAgent\Platforms::TIZEN,
            \donatj\UserAgent\Platforms::SAILFISH,
        ];

        if (in_array($platform, $mobile, true)) {
            return 'mobile';
        }

        return 'desktop';
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
     * Get table name with prefix
     */
    protected function _getTable(string $tableName): string
    {
        return Mage::getSingleton('core/resource')->getTableName($tableName);
    }

    /**
     * Add store filter to select query if a specific store is selected
     *
     * @param Maho\Db\Select $select
     * @param string $tableAlias Table alias to use for store_id column
     */
    protected function _addStoreFilter($select, string $tableAlias = ''): void
    {
        $storeId = $this->_getStoreIdForFilter();
        if ($storeId > 0) {
            $column = $tableAlias ? "{$tableAlias}.store_id" : 'store_id';
            $select->where("{$column} = ?", $storeId);
        }
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
