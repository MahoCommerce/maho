<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Build;

/**
 * Identifies the code that is running.
 *
 * The version string alone cannot do it: `Mage::getVersion()` holds the version
 * of the next release, so every commit between two releases reports the same
 * value. The commit is read from the first source that carries one:
 *
 * - `git`: the checkout that contains the core (development, or a deployment
 *   made with git);
 * - `composer`: `vendor/composer/installed.php`, written by Composer when it
 *   installs the package, so it describes what Composer put on disk;
 * - `unknown`: nothing to read. This is the case of a package extracted from the
 *   release archive, which has no `.git`, so the commit is not knowable at
 *   runtime and the caller must not guess.
 *
 * None of the sources spawns a process: `git` is read from the files of the
 * repository, not from `git rev-parse`.
 */
final class Info
{
    public const SOURCE_GIT = 'git';
    public const SOURCE_COMPOSER = 'composer';
    public const SOURCE_UNKNOWN = 'unknown';

    private const PACKAGE_NAME = 'mahocommerce/maho';
    private const SHORT_COMMIT_LENGTH = 8;

    private static ?self $instance = null;

    private function __construct(
        private readonly ?string $commit,
        private readonly string $source,
    ) {}

    public static function get(): self
    {
        return self::$instance ??= new self(...self::resolve());
    }

    /**
     * The full commit hash, or null when the installation keeps no trace of it.
     */
    public function getCommit(): ?string
    {
        return $this->commit;
    }

    public function getShortCommit(int $length = self::SHORT_COMMIT_LENGTH): ?string
    {
        return $this->commit === null ? null : substr($this->commit, 0, $length);
    }

    /**
     * Where the commit comes from, one of the SOURCE_* constants.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * A single line for a UI or a log: the short commit, or "unknown" when there
     * is nothing to show. The source stays available through getSource() for
     * diagnostics, it is not part of the label.
     */
    public function describe(): string
    {
        return $this->getShortCommit() ?? self::SOURCE_UNKNOWN;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private static function resolve(): array
    {
        $commit = self::readGitCommit(self::packageRoot());
        if ($commit !== null) {
            return [$commit, self::SOURCE_GIT];
        }

        $commit = self::readComposerInstalledCommit();
        if ($commit !== null) {
            return [$commit, self::SOURCE_COMPOSER];
        }

        return [null, self::SOURCE_UNKNOWN];
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
        if (!\Composer\InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return null;
        }

        $reference = \Composer\InstalledVersions::getReference(self::PACKAGE_NAME);

        return $reference !== null && self::isCommitHash($reference) ? strtolower($reference) : null;
    }

    private static function isCommitHash(string $value): bool
    {
        // SHA-1 and SHA-256 repositories, plus abbreviated hashes.
        return (bool) preg_match('/^[0-9a-f]{7,64}$/i', $value);
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
