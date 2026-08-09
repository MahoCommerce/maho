<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

/**
 * Reads the worker pools declared under `<global><queue><pools>`, so a module
 * can attach its queue to a tier without touching core.
 *
 * Exactly one pool should be marked `<catch_all>1</catch_all>`: it consumes
 * every queue no other pool claims. Core makes the *slow* pool the catch-all
 * on purpose, since an unclassified handler is likelier to be a slow newcomer
 * than a latency-critical one, and forgetting to declare a tier should cost a
 * message waiting behind a feed rather than a checkout email stuck behind one.
 */
final class PoolRegistry
{
    public const FALLBACK_POOL = 'default';

    /** @var array<string, Pool>|null */
    private static ?array $pools = null;

    /**
     * @return array<string, Pool>
     */
    public static function all(): array
    {
        return self::$pools ??= self::build();
    }

    public static function get(string $name): ?Pool
    {
        return self::all()[$name] ?? null;
    }

    /** Null means the queue is orphaned and no worker will ever pick it up. */
    public static function poolFor(string $queue): ?Pool
    {
        foreach (self::all() as $pool) {
            if ($pool->consumes($queue)) {
                return $pool;
            }
        }

        return null;
    }

    /**
     * The longest redelivery window any pool declares, or null when none
     * overrides the store default. A worker that consumes every queue must not
     * requeue a claim sooner than the pool owning it would, or the handler runs
     * a second time alongside the first.
     */
    public static function widestRedeliveryWindow(): ?int
    {
        $windows = array_filter(
            array_map(static fn(Pool $pool): ?int => $pool->redeliverAfter, self::all()),
            static fn(?int $window): bool => $window !== null,
        );

        return $windows === [] ? null : max($windows);
    }

    public static function reset(): void
    {
        self::$pools = null;
    }

    /**
     * @return array<string, Pool>
     */
    private static function build(): array
    {
        $node = \Mage::getConfig()->getNode('global/queue');
        $poolsNode = $node !== false && isset($node->pools) ? $node->pools : false;

        $definitions = [];
        if ($poolsNode !== false) {
            foreach ($poolsNode->children() as $name => $child) {
                if (!self::flag($child, 'active', true)) {
                    continue;
                }
                $definitions[(string) $name] = $child;
            }
        }

        if ($definitions === []) {
            return [self::FALLBACK_POOL => new Pool(self::FALLBACK_POOL)];
        }

        uasort($definitions, fn($a, $b) => (int) $a->sort_order <=> (int) $b->sort_order);

        $catchAll = null;
        foreach ($definitions as $name => $child) {
            if (!self::flag($child, 'catch_all', false)) {
                continue;
            }
            if ($catchAll === null) {
                $catchAll = $name;
                continue;
            }
            \Mage::log(
                sprintf('Queue pool "%s" is marked catch_all but "%s" already is; dropping it', $name, $catchAll),
                \Mage::LOG_ERROR,
            );
            unset($definitions[$name]);
        }

        if ($catchAll === null) {
            \Mage::log(
                'No queue pool is marked catch_all: messages dispatched to an unrouted queue will never be consumed',
                \Mage::LOG_ERROR,
            );
        }

        $queuesByPool = self::routing($node, $definitions, $catchAll);

        // A pool with nothing routed to it would consume everything and become a
        // second catch-all, so it is dropped rather than left to compete.
        foreach ($queuesByPool as $name => $queues) {
            if ($queues === [] && $name !== $catchAll) {
                \Mage::log(sprintf('Queue pool "%s" has no queues routed to it; skipping', $name), \Mage::LOG_NOTICE);
                unset($definitions[$name], $queuesByPool[$name]);
            }
        }

        $pools = [];
        foreach ($definitions as $name => $child) {
            $excluded = [];
            if ($name === $catchAll) {
                $excluded = array_values(array_unique(array_merge(...array_values(
                    array_diff_key($queuesByPool, [$name => true]),
                ))));
            }

            $pools[$name] = new Pool(
                name: (string) $name,
                queues: $queuesByPool[$name],
                excludedQueues: $excluded,
                count: max(1, (int) ($child->count ?? 1)),
                idleTimeout: isset($child->idle_timeout) ? max(0, (int) $child->idle_timeout) : null,
                memoryLimit: trim((string) ($child->memory_limit ?? '')) ?: '256M',
                timeLimit: max(0, (int) ($child->time_limit ?? 3600)),
                redeliverAfter: isset($child->redeliver_after) ? max(0, (int) $child->redeliver_after) : null,
            );
        }

        // Every declared pool was dropped (none marked catch_all, none routed a
        // queue): without a fallback the watchdog would start nothing at all and
        // the queue would stall silently.
        return $pools === [] ? [self::FALLBACK_POOL => new Pool(self::FALLBACK_POOL)] : $pools;
    }

    /**
     * The queue to pool map, one node per queue so a module can route its own
     * queue and local.xml can retarget a single one without clobbering the rest.
     * An empty value unroutes a queue, handing it back to the catch-all.
     *
     * @param  array<string, \Mage_Core_Model_Config_Element> $definitions
     * @return array<string, list<string>>
     */
    private static function routing(\Mage_Core_Model_Config_Element|false $node, array $definitions, ?string $catchAll): array
    {
        $queuesByPool = array_fill_keys(array_keys($definitions), []);
        if ($node === false || !isset($node->routing)) {
            return $queuesByPool;
        }

        foreach ($node->routing->children() as $queue => $target) {
            $pool = trim((string) $target);
            if ($pool === '' || $pool === $catchAll) {
                continue;
            }
            if (!isset($definitions[$pool])) {
                \Mage::log(
                    sprintf('Queue "%s" is routed to unknown pool "%s"; it falls to the catch-all', $queue, $pool),
                    \Mage::LOG_ERROR,
                );
                continue;
            }
            $queuesByPool[$pool][] = (string) $queue;
        }

        return $queuesByPool;
    }

    private static function flag(\Mage_Core_Model_Config_Element $node, string $child, bool $default): bool
    {
        return isset($node->{$child}) ? (bool) (int) $node->{$child} : $default;
    }
}
