<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho;

class Profiler
{
    private static array $_timers = [];
    private static bool $_enabled = false;
    private static bool $_memory_get_usage = false;

    public static function enable(): void
    {
        self::$_enabled = true;
        self::$_memory_get_usage = function_exists('memory_get_usage');
    }

    public static function disable(): void
    {
        self::$_enabled = false;
    }

    public static function reset(string $timerName): void
    {
        self::$_timers[$timerName] = [
            'start' => false,
            'count' => 0,
            'sum' => 0,
            'realmem' => 0,
            'emalloc' => 0,
        ];
    }

    public static function resume(string $timerName): void
    {
        if (!self::$_enabled) {
            return;
        }

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (self::$_memory_get_usage) {
            self::$_timers[$timerName]['realmem_start'] = memory_get_usage(true);
            self::$_timers[$timerName]['emalloc_start'] = memory_get_usage();
        }
        self::$_timers[$timerName]['start'] = microtime(true);
        self::$_timers[$timerName]['count']++;
    }

    public static function start(string $timerName): void
    {
        self::resume($timerName);
    }

    public static function pause(string $timerName): void
    {
        if (!self::$_enabled) {
            return;
        }

        $time = microtime(true); // Get current time as quick as possible to make more accurate calculations

        if (empty(self::$_timers[$timerName])) {
            self::reset($timerName);
        }
        if (false !== self::$_timers[$timerName]['start']) {
            self::$_timers[$timerName]['sum'] += $time - self::$_timers[$timerName]['start'];
            self::$_timers[$timerName]['start'] = false;
            if (self::$_memory_get_usage) {
                self::$_timers[$timerName]['realmem'] += memory_get_usage(true) - self::$_timers[$timerName]['realmem_start'];
                self::$_timers[$timerName]['emalloc'] += memory_get_usage() - self::$_timers[$timerName]['emalloc_start'];
            }
        }
    }

    public static function stop(string $timerName): void
    {
        self::pause($timerName);
    }

    public static function fetch(string $timerName, string $key = 'sum'): false|array|int|float
    {
        if (empty(self::$_timers[$timerName])) {
            return false;
        } elseif (empty($key)) {
            return self::$_timers[$timerName];
        }
        switch ($key) {
            case 'sum':
                $sum = self::$_timers[$timerName]['sum'];
                if (self::$_timers[$timerName]['start'] !== false) {
                    $sum += microtime(true) - self::$_timers[$timerName]['start'];
                }
                return $sum;

            case 'count':
                $count = self::$_timers[$timerName]['count'];
                return $count;

            case 'realmem':
                if (!isset(self::$_timers[$timerName]['realmem'])) {
                    self::$_timers[$timerName]['realmem'] = -1;
                }
                return self::$_timers[$timerName]['realmem'];

            case 'emalloc':
                if (!isset(self::$_timers[$timerName]['emalloc'])) {
                    self::$_timers[$timerName]['emalloc'] = -1;
                }
                return self::$_timers[$timerName]['emalloc'];

            default:
                if (!empty(self::$_timers[$timerName][$key])) {
                    return self::$_timers[$timerName][$key];
                }
        }
        return false;
    }

    public static function getTimers(): array
    {
        return self::$_timers;
    }

    /**
     * Output SQL Profiler
     */
    public static function getSqlProfiler(mixed $res): string
    {
        if (!$res) {
            return '';
        }
        $out = '';
        $profiler = $res->getProfiler();
        if ($profiler->getEnabled()) {
            $totalTime    = $profiler->getTotalElapsedSecs();
            $queryCount   = $profiler->getTotalNumQueries();
            $longestTime  = 0;
            $longestQuery = null;

            foreach ($profiler->getQueryProfiles() as $query) {
                if ($query->getElapsedSecs() > $longestTime) {
                    $longestTime  = $query->getElapsedSecs();
                    $longestQuery = $query->getQuery();
                }
            }

            $out .= 'Executed ' . $queryCount . ' queries in ' . $totalTime . ' seconds' . '<br>';
            $out .= 'Average query length: ' . $totalTime / $queryCount . ' seconds' . '<br>';
            $out .= 'Queries per second: ' . $queryCount / $totalTime . '<br>';
            $out .= 'Longest query length: ' . $longestTime . '<br>';
            $out .= 'Longest query: <br>' . $longestQuery . '<hr>';
        }
        return $out;
    }
}
