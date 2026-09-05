<?php

/**
 * Locates a sample data package on disk or downloads one branch of the sample data repository.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\SampleData;

use Mage;
use Maho\Exception;

final class Package
{
    public const ARCHIVE_URL = 'https://github.com/MahoCommerce/maho-sample-data/archive/refs/heads/%s.tar.gz';
    public const BRANCH_ENV = 'MAHO_SAMPLE_DATA_BRANCH';
    public const SHARED_PACK = '_shared';

    private function __construct(
        private readonly string $root,
        private readonly ?string $temporaryDir,
    ) {}

    /**
     * A checkout or an extracted archive: the folder that holds packs/ and media/.
     */
    public static function fromPath(string $path): self
    {
        $root = realpath($path);
        if ($root === false || !is_dir($root . '/packs')) {
            throw new Exception("'$path' is not a sample data package: no packs/ folder inside");
        }
        return new self($root, null);
    }

    /**
     * The repository branch that matches a Maho version, unless MAHO_SAMPLE_DATA_BRANCH says otherwise.
     */
    public static function branchForVersion(string $version): string
    {
        $env = getenv(self::BRANCH_ENV);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $parts = explode('.', $version);
        return $parts[0] . '.' . ($parts[1] ?? '0');
    }

    /**
     * Downloads and extracts one branch under var/tmp; call cleanup() when done.
     */
    public static function forBranch(string $branch, ?callable $log = null): self
    {
        $log ??= static function (string $message): void {};
        $url = sprintf(self::ARCHIVE_URL, $branch);
        $workDir = Mage::getBaseDir('var') . '/tmp/sample-data-' . preg_replace('/[^a-z0-9.]+/i', '-', $branch);
        self::removeDir($workDir);
        if (!mkdir($workDir, 0777, true) && !is_dir($workDir)) {
            throw new Exception("cannot create $workDir");
        }
        $archive = $workDir . '/package.tar.gz';
        $log("Downloading $url");
        if (@copy($url, $archive) === false) {
            self::removeDir($workDir);
            throw new Exception("cannot download the sample data branch '$branch' from $url");
        }
        $log('Extracting the archive');
        exec('tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($workDir) . ' 2>&1', $output, $status);
        unlink($archive);
        if ($status !== 0) {
            self::removeDir($workDir);
            throw new Exception('cannot extract the sample data archive: ' . implode("\n", $output));
        }
        foreach (glob($workDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_dir($dir . '/packs')) {
                return new self($dir, $workDir);
            }
        }
        self::removeDir($workDir);
        throw new Exception("the sample data branch '$branch' has no packs/ folder; this Maho version needs the CSV pack format");
    }

    public function root(): string
    {
        return $this->root;
    }

    public function packsDir(): string
    {
        return $this->root . '/packs';
    }

    public function sharedDir(): string
    {
        return $this->packsDir() . '/' . self::SHARED_PACK;
    }

    public function mediaDir(): string
    {
        return $this->root . '/media';
    }

    public function packDir(string $pack): string
    {
        return $this->packsDir() . '/' . $pack;
    }

    /**
     * Pack folder names in alphabetical order, without the shared one.
     *
     * @return list<string>
     */
    public function packs(): array
    {
        $packs = [];
        foreach (glob($this->packsDir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($name !== self::SHARED_PACK && !str_starts_with($name, '.')) {
                $packs[] = $name;
            }
        }
        sort($packs);
        return $packs;
    }

    public function cleanup(): void
    {
        if ($this->temporaryDir !== null) {
            self::removeDir($this->temporaryDir);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
