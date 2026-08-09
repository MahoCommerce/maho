<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

/**
 * Identifies the worker holding a claim, as machine:lock-name, so crash
 * recovery can read the worker's lock instead of guessing from elapsed time.
 * A worker stamps this on `claimed_by` only while it holds that lock, and the
 * kernel frees the flock however the process ends.
 *
 * The machine part is the hashed hostname: two servers on one database must
 * never share an id, or each would probe its own locks for the other's claims.
 */
final class WorkerIdentity
{
    private static ?string $machine = null;

    /** Null when the hostname is unknown, which falls back to the timer. */
    public static function forLock(string $lockName): ?string
    {
        $machine = self::machine();

        return $machine === null ? null : $machine . ':' . $lockName;
    }

    /**
     * Every id a worker on this machine can hold, alive or not.
     *
     * @return list<string>
     */
    public static function localIds(): array
    {
        return array_values(array_filter(array_map(self::forLock(...), self::lockNames())));
    }

    /**
     * The ids in localIds() whose lock nobody holds, so whose process is gone.
     *
     * @return list<string>
     */
    public static function deadLocalIds(): array
    {
        $lock = \Mage::getSingleton('core/lock');
        $free = array_filter(
            self::lockNames(),
            static fn(string $name): bool => !$lock->isHeld($name, machineLocal: true),
        );

        return array_values(array_filter(array_map(self::forLock(...), $free)));
    }

    /**
     * One per pool worker, plus the poolless lock a hand-run
     * `queue:work --exclusive` takes.
     *
     * @return list<string>
     */
    private static function lockNames(): array
    {
        $names = [Pool::LOCK_PREFIX];
        foreach (PoolRegistry::all() as $pool) {
            for ($index = 0; $index < $pool->count; $index++) {
                $names[] = $pool->lockName($index);
            }
        }

        return $names;
    }

    private static function machine(): ?string
    {
        if (self::$machine === null) {
            $hostname = gethostname();
            self::$machine = $hostname === false ? '' : substr(md5($hostname), 0, 12);
        }

        return self::$machine === '' ? null : self::$machine;
    }
}
