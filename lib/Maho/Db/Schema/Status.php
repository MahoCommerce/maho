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

final class Status
{
    /**
     * core_resource row holding the fingerprint of the schema last applied.
     * A pseudo-resource: no module owns it, so it never collides with the
     * per-module setup versions stored alongside it.
     */
    public const RESOURCE_CODE = 'declarative_schema';

    /**
     * Fingerprint of the declared schema: the contents of every active
     * module's sql/schema.php plus the table prefix, which the Collector
     * folds into the target. Cheap by design (~1ms for the ~50 core files),
     * so the request path can check it without touching the database.
     */
    public static function fingerprint(): string
    {
        $parts = [Collector::tablePrefix()];
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
                && Applier::plan($adapter->getConnection(), $target, Collector::tablePrefix()) !== [];
        } catch (UnsupportedMigrationException) {
            return false;
        }

        if ($pending) {
            return false;
        }

        self::markApplied($adapter, $fingerprint);
        return true;
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
        if (self::appliedFingerprint($adapter) === null) {
            $adapter->insert($table, ['code' => self::RESOURCE_CODE, 'version' => $fingerprint]);
        } else {
            $adapter->update($table, ['version' => $fingerprint], ['code = ?' => self::RESOURCE_CODE]);
        }
    }

    private static function appliedFingerprint(AdapterInterface $adapter): ?string
    {
        $select = $adapter->select()
            ->from(self::table(), ['version'])
            ->where('code = ?', self::RESOURCE_CODE);

        $applied = $adapter->fetchOne($select);

        return is_string($applied) && $applied !== '' ? $applied : null;
    }

    private static function table(): string
    {
        return Collector::tablePrefix() . 'core_resource';
    }
}
