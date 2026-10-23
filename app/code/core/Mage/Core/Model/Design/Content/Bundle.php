<?php

/**
 * Builds a copy of the css/styles.css of each Tailwind theme that also has the classes of the
 * storefront HTML in the database, and gives its URL to the page head.
 *
 * The copy replaces the committed file, so the utilities stay in the order of one Tailwind
 * build. It needs the Tailwind CLI in node_modules. Without it, the store serves the committed
 * file. The manifest records, for each theme, the hash of the committed file that the copy
 * came from. A copy whose committed file changed is not served.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

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
    /** Seconds before a queued build runs, so the saves of an import give one build. */
    public const BUILD_DELAY = 60;
    public const BUILD_TIMEOUT = 600;

    public const STATUS_BUILT = 'built';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';

    /** @var array<string, array{file: ?string, tokens: string, styles: string}>|null */
    protected ?array $manifest = null;

    /**
     * The path of the Tailwind CLI, or null when the toolchain is not installed.
     */
    public function getTailwindBinary(): ?string
    {
        $binary = Mage::getBaseDir() . '/node_modules/.bin/tailwindcss';
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
        $tokensHash = sha1(implode("\n", $tokens));
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
            ) {
                $built[$theme] = $previous;
                $status[$theme] = self::STATUS_UNCHANGED;
                continue;
            }

            $file = $this->compile($binary, $workDir, $theme, $paths['entry']);
            if ($file === null) {
                if ($previous !== null) {
                    $built[$theme] = $previous;
                }
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
        $process->run();
        $css = is_file($output) ? (string) file_get_contents($output) : '';
        @unlink($output);
        if (!$process->isSuccessful() || $css === '') {
            Mage::log(
                "The Tailwind build of the content for {$theme} failed: " . trim($process->getErrorOutput() . "\n" . $process->getOutput()),
                Mage::LOG_ERROR,
            );
            return null;
        }

        $file = self::FOLDER . "/{$name}-" . substr(hash('xxh128', $css), 0, 16) . '.css';
        Mage::getStorage(self::MOUNT)->moveAtomic($file, $css);
        return $file;
    }

    /**
     * Delete the files that neither the new nor the previous manifest names. The previous file
     * stays for the pages that the full-page cache holds.
     *
     * @param array<string, array{file: ?string, tokens: string, styles: string}> $previous
     * @param array<string, array{file: ?string, tokens: string, styles: string}> $current
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
     * The URL of the copy that replaces $stylesFile, the absolute path of a resolved
     * css/styles.css, or null when the store serves the committed file.
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
        if ($entry === null || $entry['file'] === null || !is_file($stylesFile)
            || hash_file('xxh128', $stylesFile) !== $entry['styles']
        ) {
            return null;
        }

        return Mage::getStorage(self::MOUNT)->publicUrl($entry['file']);
    }

    /**
     * The package/theme of each theme whose committed css/styles.css changed after its last
     * build, or that has no build.
     *
     * @return list<string>
     */
    public function getOutdatedThemes(): array
    {
        $manifest = $this->loadManifest();
        $outdated = [];
        foreach ($this->getThemes() as $theme => $paths) {
            if (is_file($paths['styles'])
                && ($manifest[$theme]['styles'] ?? null) !== hash_file('xxh128', $paths['styles'])
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
     * The manifest from the cache, or from the database when the cache has no entry.
     *
     * @return array<string, array{file: ?string, tokens: string, styles: string}>
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
        Mage::app()->saveCache(Mage::helper('core')->jsonEncode($manifest), self::CACHE_ID, [Mage_Core_Model_Config::CACHE_TAG]);
        return $this->manifest = $manifest;
    }

    /**
     * @return array<string, array{file: ?string, tokens: string, styles: string}>
     */
    protected function loadManifest(): array
    {
        $data = Mage::getModel('core/flag', ['flag_code' => self::FLAG_MANIFEST])->loadSelf()->getFlagData();
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array{file: ?string, tokens: string, styles: string}> $manifest
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
