<?php

/**
 * The commit of the code on disk.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Build;

/**
 * `Mage::getVersion()` is the version of the next release, so it cannot tell two
 * builds apart. The commit can. No source starts a process or opens a connection.
 */
final class Info
{
    private const PACKAGE_NAME = 'mahocommerce/maho';
    private const SHORT_COMMIT_LENGTH = 8;

    private static ?self $instance = null;

    private function __construct(
        private readonly ?string $commit,
    ) {}

    public static function get(): self
    {
        return self::$instance ??= new self(self::readGitCommit(self::packageRoot()) ?? self::readComposerInstalledCommit());
    }

    /**
     * The full commit hash, or null when the installation keeps no trace of it.
     */
    public function getCommit(): ?string
    {
        return $this->commit;
    }

    public function getShortCommit(): ?string
    {
        return $this->commit === null ? null : substr($this->commit, 0, self::SHORT_COMMIT_LENGTH);
    }

    /**
     * The short commit, or "unknown" when the installation keeps no trace of it.
     */
    public function getBuild(): string
    {
        return $this->getShortCommit() ?? 'unknown';
    }

    /**
     * The root of the package, whatever directory it is installed in.
     */
    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function readGitCommit(string $root): ?string
    {
        $gitDir = self::findGitDir($root);
        if ($gitDir === null) {
            return null;
        }

        // A worktree keeps HEAD in its own git dir and refs in the common one.
        $refsBase = self::resolveCommonDir($gitDir);

        $head = trim(self::readFile($gitDir . '/HEAD') ?? '');
        if ($head === '') {
            return null;
        }

        if (!str_starts_with($head, 'ref:')) {
            return self::isCommitHash($head) ? strtolower($head) : null;
        }

        $ref = trim(substr($head, 4));
        if ($ref === '') {
            return null;
        }

        $refFile = $refsBase . '/' . $ref;
        if (is_readable($refFile)) {
            $hash = trim(self::readFile($refFile) ?? '');
            return self::isCommitHash($hash) ? strtolower($hash) : null;
        }

        return self::readPackedRef($refsBase . '/packed-refs', $ref);
    }

    private static function findGitDir(string $root): ?string
    {
        $dotGit = $root . '/.git';
        if (is_dir($dotGit)) {
            return $dotGit;
        }

        // A submodule or a linked worktree has a .git file pointing at the real dir.
        if (!is_file($dotGit)) {
            return null;
        }

        $contents = trim(self::readFile($dotGit) ?? '');
        if (!str_starts_with($contents, 'gitdir:')) {
            return null;
        }

        $gitDir = self::resolvePath(trim(substr($contents, 7)), $root);
        return is_dir($gitDir) ? $gitDir : null;
    }

    private static function resolveCommonDir(string $gitDir): string
    {
        $commonDir = trim(self::readFile($gitDir . '/commondir') ?? '');

        return $commonDir === '' ? $gitDir : self::resolvePath($commonDir, $gitDir);
    }

    private static function readPackedRef(string $packedRefs, string $ref): ?string
    {
        if (!is_readable($packedRefs)) {
            return null;
        }

        foreach (explode("\n", self::readFile($packedRefs) ?? '') as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (($parts[1] ?? null) === $ref && self::isCommitHash($parts[0])) {
                return strtolower($parts[0]);
            }
        }

        return null;
    }

    private static function readComposerInstalledCommit(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled(self::PACKAGE_NAME) || self::isInstalledFromPathRepository()) {
            return null;
        }

        $reference = \Composer\InstalledVersions::getReference(self::PACKAGE_NAME);

        return $reference !== null && self::isCommitHash($reference) ? strtolower($reference) : null;
    }

    /**
     * Composer records a hash of the folder for a path repository, not a commit.
     */
    private static function isInstalledFromPathRepository(): bool
    {
        /** @var array{packages?: list<array{name?: string, dist?: array{type?: string}}>}|null $installed */
        $installed = json_decode(self::readFile(\Maho::getBasePath() . '/vendor/composer/installed.json') ?? '', true);

        foreach ($installed['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === self::PACKAGE_NAME) {
                return ($package['dist']['type'] ?? null) === 'path';
            }
        }

        return false;
    }

    private static function isCommitHash(string $value): bool
    {
        // SHA-1 and SHA-256 repositories.
        return (bool) preg_match('/^([0-9a-f]{40}|[0-9a-f]{64})$/i', $value);
    }

    private static function resolvePath(string $path, string $base): string
    {
        if ($path === '') {
            return $base;
        }

        if ($path[0] === '/') {
            return rtrim($path, '/');
        }

        return rtrim($base . '/' . $path, '/');
    }

    private static function readFile(string $file): ?string
    {
        if (!is_readable($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return $contents === false ? null : $contents;
    }
}
