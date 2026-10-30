<?php

/**
 * Builds a copy of the css/styles.css of each Tailwind theme that also has the classes of the
 * storefront HTML in the database, and gives its URL to the page head.
 *
 * The copy replaces the committed file, so the utilities stay in the order of one Tailwind
 * build. A relative URL in the CSS, such as a font, points at the same file from the new
 * directory. It needs the Tailwind CLI in node_modules. Without it, the store serves the committed
 * file. The manifest records, for each theme, the hash of the committed file that the copy
 * came from. A copy whose committed file changed is not served.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class Mage_Core_Model_Design_Content_Bundle
{
    public const FLAG_MANIFEST = 'theme_content_css';
    public const FLAG_REQUEST = 'theme_content_request';
    public const CACHE_ID = 'theme_content_css';
    public const LOCK_NAME = 'theme_content_build';
    public const QUEUE_NAME = 'theme_content';
    public const MOUNT = 'media';
    public const FOLDER = 'theme';
    public const STYLES_FILE = 'css/styles.css';
    public const ENTRY_FILE = 'src/tailwind.css';
    /** The Tailwind CLI, relative to the project root. */
    public const TAILWIND_BINARY = 'node_modules/.bin/tailwindcss';
    /** Seconds before a queued build runs, so the saves of an import give one build. */
    public const BUILD_DELAY = 60;
    public const BUILD_TIMEOUT = 600;

    public const STATUS_BUILT = 'built';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';

    /** @var array<string, array{file: ?string, tokens: string, styles: string, failed?: string}>|null */
    protected ?array $manifest = null;

    /**
     * The path of the Tailwind CLI, or null when the toolchain is not installed.
     */
    public function getTailwindBinary(): ?string
    {
        $binary = Mage::getBaseDir() . '/' . self::TAILWIND_BINARY;
        return is_file($binary) ? $binary : null;
    }

    /**
     * Each frontend theme that has a Tailwind build entry, as package/theme => paths.
     *
     * @return array<string, array{entry: string, styles: string}>
     */
    public function getThemes(): array
    {
        $skinDir = $this->getSkinDir();
        $themes = [];
        foreach (glob($skinDir . '/*/*/' . self::ENTRY_FILE) ?: [] as $entry) {
            $themeDir = dirname($entry, 2);
            $themes[substr($themeDir, strlen($skinDir) + 1)] = [
                'entry' => $entry,
                'styles' => $themeDir . '/' . self::STYLES_FILE,
            ];
        }
        ksort($themes);
        return $themes;
    }

    /**
     * Build the copy of each theme whose content or committed file changed.
     *
     * @return array<string, string>|null a STATUS_* constant by package/theme, or null when another build holds the lock
     */
    public function build(): ?array
    {
        $binary = $this->getTailwindBinary();
        if ($binary === null) {
            Mage::log('The Tailwind CLI is not installed, so the classes of the content were not compiled. Run "npm install".', Mage::LOG_WARNING);
            return [];
        }

        $lock = Mage::getSingleton('core/lock');
        if (!$lock->acquire(self::LOCK_NAME)) {
            return null;
        }

        try {
            return $this->buildThemes($binary);
        } finally {
            $lock->release(self::LOCK_NAME);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function buildThemes(string $binary): array
    {
        $tokens = Mage::getSingleton('core/design_content_collector')->collectTokens();
        $tokensHash = sha1($this->getSkinUrl() . "\n" . $this->getCopyUrl() . "\n" . implode("\n", $tokens));
        $workDir = Mage::getBaseDir('tmp') . '/theme_content';
        if (!is_dir($workDir)) {
            mkdir($workDir, 0777, true);
        }
        file_put_contents($workDir . '/content.txt', implode("\n", $tokens));

        $manifest = $this->loadManifest();
        $built = [];
        $status = [];
        foreach ($this->getThemes() as $theme => $paths) {
            if (!is_file($paths['styles'])) {
                continue;
            }
            $stylesHash = hash_file('xxh128', $paths['styles']);
            $previous = $manifest[$theme] ?? null;

            if ($tokens === []) {
                $built[$theme] = ['file' => null, 'tokens' => $tokensHash, 'styles' => $stylesHash];
                $status[$theme] = self::STATUS_EMPTY;
                continue;
            }
            if ($previous !== null && $previous['file'] !== null
                && $previous['tokens'] === $tokensHash && $previous['styles'] === $stylesHash
                && Mage::getStorage(self::MOUNT)->fileExists($previous['file'])
            ) {
                $built[$theme] = ['file' => $previous['file'], 'tokens' => $tokensHash, 'styles' => $stylesHash];
                $status[$theme] = self::STATUS_UNCHANGED;
                continue;
            }

            $file = $this->compile($binary, $workDir, $theme, $paths['entry']);
            if ($file === null) {
                // The store keeps the previous file. A save tries again, the cron job only after a new styles.css
                $built[$theme] = ['failed' => $stylesHash] + ($previous ?? ['file' => null, 'tokens' => '', 'styles' => '']);
                $status[$theme] = self::STATUS_FAILED;
                continue;
            }
            $built[$theme] = ['file' => $file, 'tokens' => $tokensHash, 'styles' => $stylesHash];
            $status[$theme] = self::STATUS_BUILT;
        }

        $this->saveManifest($built);
        $this->deleteOldFiles($manifest, $built);

        return $status;
    }

    /**
     * Compile the entry of $theme with the content tokens and write the result to the mount.
     *
     * @return string|null the path of the file on the mount, or null when Tailwind failed
     */
    protected function compile(string $binary, string $workDir, string $theme, string $entry): ?string
    {
        $name = str_replace('/', '-', $theme);
        $input = "{$workDir}/{$name}.css";
        $output = "{$workDir}/{$name}.out.css";
        file_put_contents($input, '@import ' . json_encode($entry, JSON_UNESCAPED_SLASHES) . ";\n@source \"./content.txt\";\n");

        $process = new Process([$binary, '-i', $input, '-o', $output, '--minify'], Mage::getBaseDir(), null, null, self::BUILD_TIMEOUT);
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            @unlink($output);
            Mage::log("The Tailwind build of the content for {$theme} took more than " . self::BUILD_TIMEOUT . ' seconds.', Mage::LOG_ERROR);
            return null;
        }
        $css = is_file($output) ? (string) file_get_contents($output) : '';
        @unlink($output);
        if (!$process->isSuccessful() || $css === '') {
            Mage::log(
                "The Tailwind build of the content for {$theme} failed: " . trim($process->getErrorOutput() . "\n" . $process->getOutput()),
                Mage::LOG_ERROR,
            );
            return null;
        }

        $css = $this->rebaseUrls($css, $this->getSkinUrl() . $theme . '/' . dirname(self::STYLES_FILE) . '/', $this->getCopyUrl());

        $file = self::FOLDER . "/{$name}-" . substr(hash('xxh128', $css), 0, 16) . '.css';
        Mage::getStorage(self::MOUNT)->moveAtomic($file, $css);
        return $file;
    }

    /**
     * The URL of the frontend skin directory of the default store view, with a trailing slash.
     */
    protected function getSkinUrl(): string
    {
        return rtrim(Mage::app()->getDefaultStoreView()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN), '/') . '/frontend/';
    }

    /**
     * The URL of the directory of the copies, with a trailing slash.
     */
    protected function getCopyUrl(): string
    {
        $url = (string) strstr(Mage::getStorage(self::MOUNT)->publicUrl(self::FOLDER . '/theme.css') . '?', '?', true);
        return substr($url, 0, (int) strrpos($url, '/') + 1);
    }

    /**
     * Change each relative URL in $css, so that it points at the same file from the directory
     * $toUrl. $fromUrl is the directory that the relative URLs of $css start from. A URL on
     * another host stays absolute.
     */
    public function rebaseUrls(string $css, string $fromUrl, string $toUrl): string
    {
        $from = parse_url($fromUrl);
        $to = parse_url($toUrl);
        if (!isset($from['scheme'], $from['host'], $to['scheme'], $to['host'])) {
            return $css;
        }
        $origin = fn(array $url): string => $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');
        $sameOrigin = $origin($from) === $origin($to);

        return (string) preg_replace_callback(
            '~url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\'\s)][^)]*?))\s*\)~i',
            function (array $match) use ($from, $to, $origin, $sameOrigin): string {
                $quote = $match[1] !== null ? '"' : ($match[2] !== null ? "'" : '');
                $value = $match[1] ?? $match[2] ?? $match[3];
                if ($value === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i', $value)) {
                    return $match[0];
                }

                preg_match('~^([^?#]*)(.*)$~s', $value, $parts);
                $target = [];
                foreach (explode('/', ($from['path'] ?? '/') . $parts[1]) as $segment) {
                    if ($segment === '..') {
                        array_pop($target);
                    } elseif ($segment !== '' && $segment !== '.') {
                        $target[] = $segment;
                    }
                }
                $trailing = str_ends_with($parts[1], '/') ? '/' : '';

                if (!$sameOrigin) {
                    $url = $origin($from) . '/' . implode('/', $target) . $trailing;
                } else {
                    $base = array_values(array_filter(explode('/', $to['path'] ?? '/'), fn(string $segment): bool => $segment !== ''));
                    $common = 0;
                    while (isset($base[$common], $target[$common]) && $base[$common] === $target[$common]) {
                        $common++;
                    }
                    $url = str_repeat('../', count($base) - $common) . implode('/', array_slice($target, $common)) . $trailing;
                }
                return 'url(' . $quote . $url . $parts[2] . $quote . ')';
            },
            $css,
            flags: PREG_UNMATCHED_AS_NULL,
        );
    }

    /**
     * Delete the files that neither the new nor the previous manifest names. The previous file
     * stays for the pages that the full-page cache holds.
     *
     * @param array<string, array{file: ?string, tokens: string, styles: string, failed?: string}> $previous
     * @param array<string, array{file: ?string, tokens: string, styles: string, failed?: string}> $current
     */
    protected function deleteOldFiles(array $previous, array $current): void
    {
        $keep = array_filter([...array_column($previous, 'file'), ...array_column($current, 'file')]);
        $mount = Mage::getStorage(self::MOUNT);
        foreach ($mount->listFiles(self::FOLDER) as $file) {
            if (!in_array($file->path(), $keep, true)) {
                $mount->delete($file->path());
            }
        }
    }

    /**
     * The URL of the copy that replaces $stylesFile, or null when the store serves the
     * committed file. $stylesFile is the absolute path of a resolved css/styles.css.
     */
    public function getUrl(string $stylesFile): ?string
    {
        $manifest = $this->getManifest();
        if ($manifest === []) {
            return null;
        }

        $skinDir = $this->getSkinDir() . '/';
        $suffix = '/' . self::STYLES_FILE;
        if (!str_starts_with($stylesFile, $skinDir) || !str_ends_with($stylesFile, $suffix)) {
            return null;
        }
        $entry = $manifest[substr($stylesFile, strlen($skinDir), -strlen($suffix))] ?? null;
        if ($entry === null || $entry['file'] === null) {
            return null;
        }

        return Mage::getStorage(self::MOUNT)->publicUrl($entry['file']);
    }

    /**
     * The package/theme of each theme whose committed css/styles.css changed after its last
     * build, that has no build, or whose built file is not on the mount. A theme whose build
     * failed with the current css/styles.css is not outdated, so the cron job does not repeat
     * a build that fails each time. A save of the content or theme:content:build tries again.
     *
     * @return list<string>
     */
    public function getOutdatedThemes(): array
    {
        $manifest = $this->loadManifest();
        $mount = Mage::getStorage(self::MOUNT);
        $outdated = [];
        foreach ($this->getThemes() as $theme => $paths) {
            if (!is_file($paths['styles'])) {
                continue;
            }
            $stylesHash = hash_file('xxh128', $paths['styles']);
            if (($manifest[$theme]['failed'] ?? null) === $stylesHash) {
                continue;
            }
            $file = $manifest[$theme]['file'] ?? null;
            if (($manifest[$theme]['styles'] ?? null) !== $stylesHash
                || ($file !== null && !$mount->fileExists($file))
            ) {
                $outdated[] = $theme;
            }
        }
        return $outdated;
    }

    /**
     * Queue a build, and record the time of the request. A build that started before this
     * time queues one more build when it ends. Nothing happens without the Tailwind CLI.
     */
    public function queueBuild(): void
    {
        if ($this->getTailwindBinary() === null) {
            return;
        }

        Mage::getModel('core/flag', ['flag_code' => self::FLAG_REQUEST])->loadSelf()
            ->setFlagData(time())
            ->save();
        \Maho\Queue\QueueManager::dispatch(
            new Mage_Core_Model_Design_Content_BuildMessage(),
            delaySeconds: self::BUILD_DELAY,
            queue: self::QUEUE_NAME,
            dedupeKey: self::QUEUE_NAME,
        );
    }

    /**
     * The time of the last queueBuild(), as a Unix timestamp.
     */
    public function getRequestTime(): int
    {
        return (int) Mage::getModel('core/flag', ['flag_code' => self::FLAG_REQUEST])->loadSelf()->getFlagData();
    }

    /**
     * The manifest from the cache, or from the database when the cache has no entry. A file
     * that comes from another version of the committed css/styles.css, such as after a deploy,
     * or that the mount does not have, such as after a restore of the database alone, is left
     * out, so the head links the committed file. The check runs when the cache is filled,
     * not on each page.
     *
     * @return array<string, array{file: ?string, tokens: string, styles: string, failed?: string}>
     */
    public function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        // Before the install, there is no flag table
        if (!Mage::isInstalled()) {
            return [];
        }

        $cached = Mage::app()->loadCache(self::CACHE_ID);
        if (is_string($cached) && $cached !== '') {
            try {
                return $this->manifest = (array) Mage::helper('core')->jsonDecode($cached);
            } catch (\JsonException) {
            }
        }

        $manifest = $this->loadManifest();
        $mount = Mage::getStorage(self::MOUNT);
        foreach ($manifest as $theme => $entry) {
            $stylesFile = $this->getSkinDir() . "/{$theme}/" . self::STYLES_FILE;
            if ($entry['file'] !== null && (!is_file($stylesFile)
                || hash_file('xxh128', $stylesFile) !== $entry['styles']
                || !$mount->fileExists($entry['file']))
            ) {
                $manifest[$theme]['file'] = null;
            }
        }
        Mage::app()->saveCache(Mage::helper('core')->jsonEncode($manifest), self::CACHE_ID, [Mage_Core_Model_Config::CACHE_TAG]);
        return $this->manifest = $manifest;
    }

    /**
     * @return array<string, array{file: ?string, tokens: string, styles: string, failed?: string}>
     */
    protected function loadManifest(): array
    {
        $data = Mage::getModel('core/flag', ['flag_code' => self::FLAG_MANIFEST])->loadSelf()->getFlagData();
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array{file: ?string, tokens: string, styles: string, failed?: string}> $manifest
     */
    protected function saveManifest(array $manifest): void
    {
        Mage::getModel('core/flag', ['flag_code' => self::FLAG_MANIFEST])->loadSelf()
            ->setFlagData($manifest)
            ->save();
        Mage::app()->removeCache(self::CACHE_ID);
        $this->manifest = null;
    }

    protected function getSkinDir(): string
    {
        return Mage::getBaseDir('skin') . '/frontend';
    }
}
