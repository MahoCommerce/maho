<?php

/**
 * Convergence state of the declarative schema against the live database.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Expr;
use RuntimeException;

final class Status
{
    /**
     * core_resource row holding the fingerprint of the schema last applied.
     * A pseudo-resource: no module owns it, so it never collides with the
     * per-module setup versions stored alongside it.
     */
    public const RESOURCE_CODE = 'declarative_schema';

    /**
     * Files that shape the target schema and the plan derived from it. They
     * move the goalposts without a single sql/schema.php changing (a new
     * table default, a different canonicalization rule), so they count
     * towards the fingerprint like the declarations themselves.
     */
    private const PIPELINE_FILES = ['Collector.php', 'Canonicalizer.php', 'Applier.php'];

    /**
     * Fingerprint of the declared schema: the contents of every active
     * module's sql/schema.php, the table prefix the Collector folds into the
     * target, and the pipeline that turns the two into a plan. Cheap by
     * design (~1ms for the ~50 core files), so the request path can check it
     * without touching the database.
     */
    public static function fingerprint(): string
    {
        $parts = [Collector::tablePrefix()];
        foreach (self::PIPELINE_FILES as $name) {
            $parts[] = $name . ':' . hash_file('xxh128', __DIR__ . '/' . $name);
        }
        foreach (Collector::sourceFiles() as $module => $file) {
            $parts[] = $module . ':' . hash_file('xxh128', $file);
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * Whether the database already carries everything the declared schema
     * asks for. A fingerprint match answers without a query; otherwise the
     * planner decides, since a schema file can change without the database
     * needing anything (a comment, a reordering, a rewritten declaration that
     * produces the same table). In that case the new fingerprint is recorded
     * so the introspection is paid once rather than on every request.
     *
     * A plan that cannot be computed counts as pending: it needs a human, and
     * `./maho migrate --dry-run` is where they get told why.
     *
     * Storage-engine conversions are deliberately left out of the verdict.
     * They are the one part of the plan that looks beyond the declared tables
     * (Applier::legacyEngineTables scans the whole database), so a stray
     * MyISAM table belonging to a third-party module would otherwise report
     * the declared schema as behind. `./maho migrate` still converts them.
     */
    public static function isConverged(AdapterInterface $adapter, ?string $fingerprint = null): bool
    {
        $fingerprint ??= self::fingerprint();
        if (self::appliedFingerprint($adapter) === $fingerprint) {
            return true;
        }

        try {
            [$target, $contributors] = Collector::collect();
            $pending = $contributors !== []
                && Applier::plan($adapter->getConnection(), $target, Collector::tablePrefix(), false) !== [];
        } catch (RuntimeException) {
            return false;
        }

        if ($pending) {
            return false;
        }

        self::markApplied($adapter, $fingerprint);
        return true;
    }

    /**
     * Whether $fingerprint is the one already recorded as applied. A single
     * primary-key lookup with no introspection: the cheap re-check a refused
     * request makes before trusting a cached "pending" verdict, since
     * `./maho migrate` converges the database without changing the
     * fingerprint that verdict was cached under.
     */
    public static function isRecorded(AdapterInterface $adapter, ?string $fingerprint = null): bool
    {
        return self::appliedFingerprint($adapter) === ($fingerprint ?? self::fingerprint());
    }

    /**
     * Record the declared schema as applied. Called by every path that
     * converges the database: the installer, `./maho migrate`, and the
     * no-op case above.
     */
    public static function markApplied(AdapterInterface $adapter, ?string $fingerprint = null): void
    {
        $table = self::table();
        if (!$adapter->isTableExists($table)) {
            return;
        }

        $fingerprint ??= self::fingerprint();
        $select = $adapter->select()
            ->from($table, new Expr('COUNT(*)'))
            ->where('code = ?', self::RESOURCE_CODE);

        // On the row, not on the value: a row whose version is NULL or empty
        // still owns the primary key, and an INSERT would collide with it.
        if ((int) $adapter->fetchOne($select) === 0) {
            $adapter->insert($table, ['code' => self::RESOURCE_CODE, 'version' => $fingerprint]);
        } else {
            $adapter->update($table, ['version' => $fingerprint], ['code = ?' => self::RESOURCE_CODE]);
        }
    }

    private static function appliedFingerprint(AdapterInterface $adapter): ?string
    {
        $table = self::table();
        // A database that predates core_resource (an empty schema behind a
        // populated local.xml) has nothing recorded; let the planner decide
        // rather than fatalling on a missing table.
        if (!$adapter->isTableExists($table)) {
            return null;
        }

        $select = $adapter->select()
            ->from($table, ['version'])
            ->where('code = ?', self::RESOURCE_CODE);

        $applied = $adapter->fetchOne($select);

        return is_string($applied) && $applied !== '' ? $applied : null;
    }

    private static function table(): string
    {
        return Collector::tablePrefix() . 'core_resource';
    }
}
