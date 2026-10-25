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
 * - `composer.lock`: the lock file of the project, for an install from a VCS or
 *   a dist archive where the installed metadata is unavailable;
 * - `unknown`: nothing to read. This is the case of a package extracted from the
 *   release archive, which has neither `.git` nor `composer.lock`, so the commit
 *   is not knowable at runtime and the caller must not guess.
 *
 * The repository the commit belongs to comes from the git remote, or from the
 * dist URL of the lock file: an installation of a fork must link to that fork,
 * not to upstream.
 *
 * None of the sources spawns a process: `git` is read from the files of the
 * repository, not from `git rev-parse`.
 */
final class Info
{
    public const SOURCE_GIT = 'git';
    public const SOURCE_COMPOSER = 'composer';
    public const SOURCE_LOCK = 'composer.lock';
    public const SOURCE_UNKNOWN = 'unknown';

    private const PACKAGE_NAME = 'mahocommerce/maho';
    private const SHORT_COMMIT_LENGTH = 8;

    private static ?self $instance = null;

    private function __construct(
        private readonly ?string $commit,
        private readonly string $source,
        private readonly ?string $repositoryUrl,
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
     * The repository the commit lives in, when the installation says which one
     * it is. A fork reports its own repository.
     */
    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
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
     * The commit page of the repository the installation came from, when both
     * the commit and the repository are known.
     */
    public function getCommitUrl(): ?string
    {
        if ($this->commit === null
            || $this->repositoryUrl === null
            || !preg_match('/^([0-9a-f]{40}|[0-9a-f]{64})$/', $this->commit)
        ) {
            return null;
        }

        $host = (string) parse_url($this->repositoryUrl, PHP_URL_HOST);

        return match (true) {
            $host === 'github.com' => $this->repositoryUrl . '/commit/' . $this->commit,
            str_contains($host, 'gitlab') => $this->repositoryUrl . '/-/commit/' . $this->commit,
            default => null,
        };
    }

    /**
     * @return array{0: ?string, 1: string, 2: ?string}
     */
    private static function resolve(): array
    {
        $packageRoot = self::packageRoot();
        $projectRoot = self::projectRoot();

        $commit = self::readGitCommit($packageRoot);
        if ($commit !== null) {
            return [$commit, self::SOURCE_GIT, self::readGitRemoteUrl($packageRoot)];
        }

        $commit = self::readComposerInstalledCommit();
        if ($commit !== null) {
            return [$commit, self::SOURCE_COMPOSER, self::readLockRepositoryUrl($projectRoot)];
        }

        $commit = self::readComposerLockCommit($projectRoot);
        if ($commit !== null) {
            return [$commit, self::SOURCE_LOCK, self::readLockRepositoryUrl($projectRoot)];
        }

        return [null, self::SOURCE_UNKNOWN, null];
    }

    /**
     * The root of the package, whatever directory it is installed in.
     */
    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function projectRoot(): string
    {
        return defined('MAHO_ROOT_DIR') ? MAHO_ROOT_DIR : self::packageRoot();
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

    private static function readGitRemoteUrl(string $root): ?string
    {
        $gitDir = self::findGitDir($root);
        if ($gitDir === null) {
            return null;
        }

        $config = self::readFile(self::resolveCommonDir($gitDir) . '/config');
        if ($config === null) {
            return null;
        }

        $remotes = [];
        $section = '';
        foreach (explode("\n", $config) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            if ($line[0] === '[') {
                $section = trim($line, '[]');
                continue;
            }

            if (!str_starts_with($section, 'remote ') || !preg_match('/^url\s*=\s*(.+)$/i', $line, $matches)) {
                continue;
            }

            $name = trim(substr($section, strlen('remote ')), '"');
            $remotes[$name] = trim($matches[1]);
        }

        $url = $remotes['origin'] ?? ($remotes === [] ? null : reset($remotes));

        return $url === null ? null : self::normalizeRepositoryUrl($url);
    }

    private static function readLockRepositoryUrl(string $root): ?string
    {
        $lockFile = $root . '/composer.lock';
        if (!is_readable($lockFile)) {
            return null;
        }

        /** @var array{packages?: list<array{name?: string, dist?: array{url?: string}, source?: array{url?: string}}>, packages-dev?: list<array{name?: string, dist?: array{url?: string}, source?: array{url?: string}}>}|null $lock */
        $lock = json_decode(self::readFile($lockFile) ?? '', true);
        if (!is_array($lock)) {
            return null;
        }

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (($package['name'] ?? null) !== self::PACKAGE_NAME) {
                continue;
            }

            // The source URL is the plain git URL, so it needs no per-host unwrapping.
            // A dist URL of a host other than GitHub is an API URL (GitLab serves it
            // from /api/v4/projects/.../archive.zip) and would produce a broken link.
            $sourceUrl = self::normalizeRepositoryUrl((string) ($package['source']['url'] ?? ''));
            if ($sourceUrl !== null) {
                return $sourceUrl;
            }

            $distUrl = (string) ($package['dist']['url'] ?? '');

            // GitHub dist URLs come from api.github.com/repos/<owner>/<repo>/zipball/<ref>.
            if (preg_match('#^https?://api\.github\.com/repos/([^/]+)/([^/]+)/#', $distUrl, $matches)) {
                return sprintf('https://github.com/%s/%s', $matches[1], $matches[2]);
            }

            return self::normalizeRepositoryUrl($distUrl);
        }

        return null;
    }

    /**
     * Turn a git remote or a dist URL into the web URL of the repository, or
     * null when it does not point at a remote repository.
     */
    private static function normalizeRepositoryUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // scp-like syntax: git@github.com:owner/repo.git
        if (preg_match('#^(?:[^@/]+@)?([^:/]+):(?!//)(.+)$#', $url, $matches)) {
            $url = sprintf('https://%s/%s', $matches[1], $matches[2]);
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');
        $path = (string) preg_replace('/\.git$/', '', $path);
        if ($path === '' || substr_count($path, '/') < 1) {
            return null;
        }

        return sprintf('https://%s/%s', $parts['host'], $path);
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
        if (!class_exists(\Composer\InstalledVersions::class)
            || !\Composer\InstalledVersions::isInstalled(self::PACKAGE_NAME)
        ) {
            return null;
        }

        $reference = \Composer\InstalledVersions::getReference(self::PACKAGE_NAME);

        return $reference !== null && self::isCommitHash($reference) ? strtolower($reference) : null;
    }

    private static function readComposerLockCommit(string $root): ?string
    {
        $lockFile = $root . '/composer.lock';
        if (!is_readable($lockFile)) {
            return null;
        }

        /** @var array{packages?: list<array{name?: string, dist?: array{reference?: string}, source?: array{reference?: string}}>, packages-dev?: list<array{name?: string, dist?: array{reference?: string}, source?: array{reference?: string}}>}|null $lock */
        $lock = json_decode(self::readFile($lockFile) ?? '', true);
        if (!is_array($lock)) {
            return null;
        }

        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        foreach ($packages as $package) {
            if (($package['name'] ?? null) !== self::PACKAGE_NAME) {
                continue;
            }

            $reference = $package['dist']['reference'] ?? $package['source']['reference'] ?? null;

            return $reference !== null && self::isCommitHash($reference) ? strtolower($reference) : null;
        }

        return null;
    }

    private static function isCommitHash(string $value): bool
    {
        // SHA-1 and SHA-256 repositories, plus the abbreviated hashes of a lock file.
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
