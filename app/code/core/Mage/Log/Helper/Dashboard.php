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
     * Get current date in UTC for database queries
     */
    protected function _getUtcDate(string $modifier = ''): string
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        if ($modifier) {
            $date->modify($modifier);
        }
        return $date->format('Y-m-d');
    }

    /**
     * Get current datetime in UTC for database queries
     */
    protected function _getUtcDateTime(string $modifier = ''): string
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        if ($modifier) {
            $date->modify($modifier);
        }
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get today's date in UTC
     */
    protected function _getUtcToday(): string
    {
        return $this->_getUtcDate();
    }

    /**
     * Get date N days ago in UTC for database queries
     */
    protected function _getUtcDaysAgo(int $days): string
    {
        return $this->_getUtcDate("-{$days} days");
    }

    /**
     * Format date for display using store locale
     */
    protected function _formatDateForDisplay(string $date, string $format = 'M j'): string
    {
        $dateTime = new DateTime($date, new DateTimeZone('UTC'));
        return $dateTime->format($format);
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
        $today = $this->_getUtcToday();
        $cacheKey = $this->_getCacheKey('today_count_' . $today);
        $count = Mage::app()->getCache()->load($cacheKey);

        if ($count === false) {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from($this->_getTable('log_visitor'), ['count' => 'COUNT(*)'])
                ->where('DATE(first_visit_at) = ?', $today);

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
        $weekStart = $this->_getUtcDaysAgo(7);
        $cacheKey = $this->_getCacheKey('week_count_' . $weekStart);
        $count = Mage::app()->getCache()->load($cacheKey);

        if ($count === false) {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from($this->_getTable('log_visitor'), ['count' => 'COUNT(*)'])
                ->where('first_visit_at >= ?', $weekStart . ' 00:00:00');

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
            $startDate = $this->_getUtcDateTime("-{$days} days");

            // Get daily aggregated data from log_summary
            $select = $adapter->select()
                ->from($this->_getTable('log_summary'), ['add_date', 'visitor_count'])
                ->where('add_date >= ?', $startDate)
                ->order('add_date ASC');

            $this->_addStoreFilter($select);

            $rows = $adapter->fetchAll($select);

            // Group by day
            $dailyData = [];
            foreach ($rows as $row) {
                $day = $this->_formatDateForDisplay($row['add_date'], 'Y-m-d');
                if (!isset($dailyData[$day])) {
                    $dailyData[$day] = 0;
                }
                $dailyData[$day] += (int) $row['visitor_count'];
            }

            // Fill in missing days with zeros
            $labels = [];
            $data = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $this->_getUtcDate("-{$i} days");
                $labels[] = $this->_formatDateForDisplay($date, 'M j');
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
                ->where('lu.visit_time >= ?', $this->_getUtcDaysAgo($days))
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
     * Get traffic sources breakdown by referrer domain
     */
    public function getTrafficSources(int $days = 7, int $limit = 10): array
    {
        $cacheKey = $this->_getCacheKey('sources_' . $days . '_' . $limit);
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
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->limit(10000);

            $this->_addStoreFilter($select, 'v');

            $visitors = $adapter->fetchAll($select);

            $storeHost = parse_url(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST);
            $sources = [];

            foreach ($visitors as $visitor) {
                $referer = $visitor['http_referer'] ?? '';
                $host = empty($referer) ? null : parse_url($referer, PHP_URL_HOST);

                // Skip internal referrers (same domain)
                if ($host === $storeHost) {
                    continue;
                }

                $domain = $host ?: 'direct';
                $sources[$domain] = ($sources[$domain] ?? 0) + 1;
            }

            arsort($sources);
            $sources = array_slice($sources, 0, $limit, true);

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
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->limit(10000);

            $this->_addStoreFilter($select, 'v');

            $visitors = $adapter->fetchAll($select);

            $devices = ['desktop' => 0, 'tablet' => 0, 'mobile' => 0];
            $browsers = [];
            $browserDetect = new \cbschuld\Browser();

            foreach ($visitors as $visitor) {
                $ua = $visitor['http_user_agent'] ?? '';
                $browserDetect->setUserAgent($ua);

                // Device detection
                if ($browserDetect->isTablet()) {
                    $device = 'tablet';
                } elseif ($browserDetect->isMobile()) {
                    $device = 'mobile';
                } else {
                    $device = 'desktop';
                }
                $devices[$device]++;

                // Browser detection
                $browser = $browserDetect->getBrowser() ?: 'Other';
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
     * Get session metrics (avg duration, pages/session, bounce rate)
     *
     * @return array ['avg_duration' => int, 'avg_pages' => float, 'bounce_rate' => float, 'total_sessions' => int]
     */
    public function getSessionMetrics(int $days = 7): array
    {
        $cacheKey = $this->_getCacheKey('session_metrics_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            // Get session data with page counts
            $select = $adapter->select()
                ->from(['v' => $this->_getTable('log_visitor')], [
                    'visitor_id',
                    'duration' => $adapter->getTimestampDiffExpr('v.first_visit_at', 'v.last_visit_at'),
                ])
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->where('v.first_visit_at != v.last_visit_at'); // Exclude single-page sessions for duration

            $this->_addStoreFilter($select, 'v');

            $sessions = $adapter->fetchAll($select);

            // Get page counts per visitor
            $pageSelect = $adapter->select()
                ->from(['lu' => $this->_getTable('log_url')], [
                    'visitor_id',
                    'page_count' => new \Maho\Db\Expr('COUNT(*)'),
                ])
                ->join(
                    ['v' => $this->_getTable('log_visitor')],
                    'lu.visitor_id = v.visitor_id',
                    [],
                )
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->group('lu.visitor_id');

            $this->_addStoreFilter($pageSelect, 'v');

            $pageCounts = $adapter->fetchPairs($pageSelect);

            // Calculate metrics
            $totalSessions = count($pageCounts);
            $totalDuration = 0;
            $totalPages = 0;
            $bounces = 0;

            foreach ($sessions as $session) {
                $totalDuration += (int) $session['duration'];
            }

            foreach ($pageCounts as $visitorId => $pages) {
                $totalPages += $pages;
                if ($pages == 1) {
                    $bounces++;
                }
            }

            $avgDuration = $totalSessions > 0 ? (int) ($totalDuration / count($sessions)) : 0;
            $avgPages = $totalSessions > 0 ? round($totalPages / $totalSessions, 1) : 0;
            $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;

            $result = Mage::helper('core')->jsonEncode([
                'avg_duration' => $avgDuration,
                'avg_pages' => $avgPages,
                'bounce_rate' => $bounceRate,
                'total_sessions' => $totalSessions,
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
     * Format duration in seconds to human readable string
     */
    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . 'm ' . $secs . 's';
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }

    /**
     * Get language breakdown from Accept-Language headers
     *
     * @return array ['languages' => [...], 'total' => int]
     */
    public function getLanguageBreakdown(int $days = 7, int $limit = 10): array
    {
        $cacheKey = $this->_getCacheKey('languages_' . $days . '_' . $limit);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $select = $adapter->select()
                ->from(['v' => $this->_getTable('log_visitor')], ['visitor_id'])
                ->join(
                    ['vi' => $this->_getTable('log_visitor_info')],
                    'v.visitor_id = vi.visitor_id',
                    ['http_accept_language'],
                )
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->where('vi.http_accept_language IS NOT NULL')
                ->where('vi.http_accept_language != ?', '')
                ->limit(10000);

            $this->_addStoreFilter($select, 'v');

            $visitors = $adapter->fetchAll($select);

            $languages = [];
            foreach ($visitors as $visitor) {
                $lang = $this->_parseAcceptLanguage($visitor['http_accept_language']);
                if ($lang) {
                    $languages[$lang] = ($languages[$lang] ?? 0) + 1;
                }
            }

            arsort($languages);
            $languages = array_slice($languages, 0, $limit, true);

            $result = Mage::helper('core')->jsonEncode([
                'languages' => $languages,
                'total' => count($visitors),
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
     * Parse Accept-Language header and return primary language
     */
    protected function _parseAcceptLanguage(string $header): ?string
    {
        // Parse "en-US,en;q=0.9,it;q=0.8" format
        $parts = explode(',', $header);
        if (empty($parts)) {
            return null;
        }

        // Get first (primary) language
        $primary = trim(explode(';', $parts[0])[0]);

        // Normalize to language code (e.g., "en-US" -> "en", "it-IT" -> "it")
        $langCode = strtolower(explode('-', $primary)[0]);

        return $langCode ?: null;
    }

    /**
     * Get human-readable language name
     */
    public function getLanguageName(string $code): string
    {
        $names = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'pl' => 'Polish',
            'tr' => 'Turkish',
            'vi' => 'Vietnamese',
            'th' => 'Thai',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'cs' => 'Czech',
            'el' => 'Greek',
            'he' => 'Hebrew',
            'hu' => 'Hungarian',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'ro' => 'Romanian',
            'sk' => 'Slovak',
            'uk' => 'Ukrainian',
        ];

        return $names[$code] ?? strtoupper($code);
    }

    /**
     * Get entry (landing) pages
     *
     * @return array [['url' => string, 'visits' => int], ...]
     */
    public function getEntryPages(int $days = 7, int $limit = 10): array
    {
        $cacheKey = $this->_getCacheKey('entry_pages_' . $days . '_' . $limit);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            // Subquery to get first URL per visitor
            $firstUrlSelect = $adapter->select()
                ->from(['lu' => $this->_getTable('log_url')], [
                    'visitor_id',
                    'first_url_id' => new \Maho\Db\Expr('MIN(lu.url_id)'),
                ])
                ->join(
                    ['v' => $this->_getTable('log_visitor')],
                    'lu.visitor_id = v.visitor_id',
                    [],
                )
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days));

            $this->_addStoreFilter($firstUrlSelect, 'v');
            $firstUrlSelect->group('lu.visitor_id');

            // Main query to get URLs and count
            $select = $adapter->select()
                ->from(['fu' => $firstUrlSelect], ['visits' => new \Maho\Db\Expr('COUNT(*)')])
                ->join(
                    ['lui' => $this->_getTable('log_url_info')],
                    'fu.first_url_id = lui.url_id',
                    ['url'],
                )
                ->where('lui.url IS NOT NULL')
                ->where('lui.url != ?', '')
                ->group('lui.url')
                ->order('visits DESC')
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
     * Get exit pages
     *
     * @return array [['url' => string, 'exits' => int], ...]
     */
    public function getExitPages(int $days = 7, int $limit = 10): array
    {
        $cacheKey = $this->_getCacheKey('exit_pages_' . $days . '_' . $limit);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            // Use last_url_id from log_visitor table
            $select = $adapter->select()
                ->from(['v' => $this->_getTable('log_visitor')], ['exits' => new \Maho\Db\Expr('COUNT(*)')])
                ->join(
                    ['lui' => $this->_getTable('log_url_info')],
                    'v.last_url_id = lui.url_id',
                    ['url'],
                )
                ->where('v.first_visit_at >= ?', $this->_getUtcDaysAgo($days))
                ->where('v.last_url_id > 0')
                ->where('lui.url IS NOT NULL')
                ->where('lui.url != ?', '');

            $this->_addStoreFilter($select, 'v');

            $select->group('lui.url')
                ->order('exits DESC')
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
     * Get customer conversion metrics
     *
     * @return array ['visitors' => int, 'customers' => int, 'conversion_rate' => float]
     */
    public function getCustomerConversion(int $days = 7): array
    {
        $cacheKey = $this->_getCacheKey('conversion_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $startDate = $this->_getUtcDaysAgo($days);

            // Total visitors
            $visitorSelect = $adapter->select()
                ->from($this->_getTable('log_visitor'), ['count' => new \Maho\Db\Expr('COUNT(*)')])
                ->where('first_visit_at >= ?', $startDate);

            $this->_addStoreFilter($visitorSelect);
            $totalVisitors = (int) $adapter->fetchOne($visitorSelect);

            // Visitors who logged in (became customers)
            $customerSelect = $adapter->select()
                ->from(['lc' => $this->_getTable('log_customer')], ['count' => new \Maho\Db\Expr('COUNT(DISTINCT lc.visitor_id)')])
                ->join(
                    ['v' => $this->_getTable('log_visitor')],
                    'lc.visitor_id = v.visitor_id',
                    [],
                )
                ->where('v.first_visit_at >= ?', $startDate);

            $this->_addStoreFilter($customerSelect, 'lc');
            $customers = (int) $adapter->fetchOne($customerSelect);

            $conversionRate = $totalVisitors > 0 ? round(($customers / $totalVisitors) * 100, 2) : 0;

            $result = Mage::helper('core')->jsonEncode([
                'visitors' => $totalVisitors,
                'customers' => $customers,
                'conversion_rate' => $conversionRate,
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
     * Get new vs returning visitors
     *
     * @return array ['new' => int, 'returning' => int, 'total' => int]
     */
    public function getNewVsReturning(int $days = 7): array
    {
        $cacheKey = $this->_getCacheKey('new_returning_' . $days);
        $result = Mage::app()->getCache()->load($cacheKey);

        if ($result === false) {
            $adapter = $this->_getReadAdapter();

            $startDate = $this->_getUtcDaysAgo($days);

            // Get unique IPs that visited in the current period
            $currentSelect = $adapter->select()
                ->distinct()
                ->from(['v' => $this->_getTable('log_visitor')], [])
                ->join(
                    ['vi' => $this->_getTable('log_visitor_info')],
                    'v.visitor_id = vi.visitor_id',
                    ['remote_addr'],
                )
                ->where('v.first_visit_at >= ?', $startDate)
                ->where('vi.remote_addr IS NOT NULL');

            $this->_addStoreFilter($currentSelect, 'v');

            $currentIps = $adapter->fetchCol($currentSelect);

            if (empty($currentIps)) {
                $result = Mage::helper('core')->jsonEncode([
                    'new' => 0,
                    'returning' => 0,
                    'total' => 0,
                ]);
            } else {
                // Get unique IPs that visited BEFORE the current period (returning visitors)
                $previousSelect = $adapter->select()
                    ->distinct()
                    ->from(['v' => $this->_getTable('log_visitor')], [])
                    ->join(
                        ['vi' => $this->_getTable('log_visitor_info')],
                        'v.visitor_id = vi.visitor_id',
                        ['remote_addr'],
                    )
                    ->where('v.first_visit_at < ?', $startDate)
                    ->where('vi.remote_addr IN (?)', $currentIps);

                $returningIps = array_flip($adapter->fetchCol($previousSelect));

                // Count new vs returning
                $returning = 0;
                $new = 0;

                foreach ($currentIps as $ip) {
                    if (isset($returningIps[$ip])) {
                        $returning++;
                    } else {
                        $new++;
                    }
                }

                $total = $new + $returning;

                $result = Mage::helper('core')->jsonEncode([
                    'new' => $new,
                    'returning' => $returning,
                    'total' => $total,
                ]);
            }

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
     * Get database read adapter
     */
    protected function _getReadAdapter(): \Maho\Db\Adapter\AdapterInterface
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
     */
    protected function _addStoreFilter(\Maho\Db\Select $select, string $tableAlias = ''): void
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
