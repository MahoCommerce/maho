<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho;

use Maho\Io\IoInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Abstract I/O class with security utilities for path validation
 */
abstract class Io implements IoInterface
{
    /**
     * If this variable is set to true, our library will be able to automatically
     * create non-existent directories
     *
     * @var bool
     */
    protected $_allowCreateFolders = false;

    /**
     * Allow automatically create non-existent directories
     *
     * @param bool $flag
     * @return $this
     */
    public function setAllowCreateFolders($flag)
    {
        $this->_allowCreateFolders = (bool) $flag;
        return $this;
    }

    /**
     * Open a connection
     *
     * @return bool
     */
    #[\Override]
    public function open(array $args = [])
    {
        return false;
    }

    /**
     * @return string
     */
    #[\Override]
    public function dirsep()
    {
        return '/';
    }

    /**
     * @param mixed $path
     * @return string
     */
    public function getCleanPath($path)
    {
        if (empty($path)) {
            return './';
        }

        $path = trim(preg_replace('/\\\\/', '/', (string) $path));

        if (!preg_match("/(\.\w{1,4})$/", $path) && !preg_match("/\?[^\\/]+$/", $path) && !preg_match('/\\/$/', $path)) {
            $path .= '/';
        }

        $matches = [];
        $pattern = "/^(\\/|\w:\\/|https?:\\/\\/[^\\/]+\\/)?(.*)$/i";
        preg_match_all($pattern, $path, $matches, PREG_SET_ORDER);

        $pathTokR = $matches[0][1];
        $pathTokP = $matches[0][2];

        $pathTokP = preg_replace(['/^\\/+/', '/\\/+/'], ['', '/'], $pathTokP);

        $pathParts = explode('/', $pathTokP);
        $realPathParts = [];

        for ($i = 0, $realPathParts = []; $i < count($pathParts); $i++) {
            if ($pathParts[$i] == '.') {
                continue;
            }
            if ($pathParts[$i] == '..') {
                if ((isset($realPathParts[0])  &&  $realPathParts[0] != '..') || ($pathTokR != '')) {
                    array_pop($realPathParts);
                    continue;
                }
            }

            $realPathParts[] = $pathParts[$i];
        }

        return $pathTokR . implode('/', $realPathParts);
    }

    /**
     * Resolve $path inside $baseDir and return the canonical absolute path, or null when it escapes.
     *
     * A relative $path is joined to $baseDir; an absolute one must already lie inside it. Dot
     * segments and backslashes are collapsed before the check, stream wrappers and null bytes are
     * refused, and the deepest existing ancestor is compared through realpath() so a symlink
     * cannot lead outside the base directory. The path itself does not need to exist.
     */
    public static function getPathWithinDir(string $baseDir, string $path): ?string
    {
        if ($baseDir === '' || $path === '' || str_contains($baseDir, "\0") || str_contains($path, "\0")) {
            return null;
        }
        if (!Path::isLocal($baseDir) || !Path::isLocal($path)) {
            return null;
        }

        $base = Path::canonicalize($baseDir);
        $normalized = str_replace('\\', '/', $path);
        $candidate = Path::isAbsolute($normalized)
            ? Path::canonicalize($normalized)
            : Path::canonicalize($base . '/' . $normalized);

        $realBase = realpath($base);
        $probe = $candidate;
        $real = realpath($probe);
        while ($real === false) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
            $real = realpath($probe);
        }

        $contained = $realBase !== false && $real !== false
            ? Path::isBasePath($realBase, $real)
            : Path::isBasePath($base, $candidate);

        return $contained ? $candidate : null;
    }

    /**
     * The mount path of $file below $directory, or null when $file is empty, holds a null byte,
     * or leaves $directory through a dot segment. A leading slash does not make $file absolute:
     * stored names such as /m/a/file.pdf start with one, so $file always stays below $directory.
     * Use it on every name that a request or a database row supplies before a read, a write or
     * a delete on a mount.
     *
     * A local mount applies getPathWithinDir() on the disk, so a symlink inside $directory
     * cannot lead outside it. A remote mount has only the key, so it applies the canonical
     * containment on that.
     */
    public static function getPathWithinMount(Storage\Mount $mount, string $directory, string $file): ?string
    {
        if ($file === '' || str_contains($file, "\0") || str_contains($directory, "\0")) {
            return null;
        }
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $file = ltrim(str_replace('\\', '/', $file), '/');

        $root = $mount->localRoot();
        if ($root !== null) {
            $base = Path::canonicalize($directory === '' ? $root : $root . '/' . $directory);
            $resolved = self::getPathWithinDir($base, $file);
            if ($resolved === null || $resolved === $base) {
                return null;
            }

            return ltrim(substr($resolved, strlen(Path::canonicalize($root))), '/');
        }

        $base = '/' . $directory;
        $candidate = Path::canonicalize($base . '/' . $file);
        if ($candidate === $base || !Path::isBasePath($base, $candidate)) {
            return null;
        }

        return ltrim($candidate, '/');
    }

    /**
     * Replace full path to relative
     *
     * @param string $path
     * @return string
     */
    public function getFilteredPath($path)
    {
        $dir = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME);
        $position = strpos($path, $dir);
        if ($position !== false && $position < 1) {
            $path = substr_replace($path, '.', 0, strlen($dir));
        }
        return $path;
    }

    /**
     * Safe wrapper for getimagesize() that prevents phar:// deserialization
     *
     * @param string $filename The file path to check
     * @return array<int|string, mixed>|false Image size info or false on failure/unsafe path
     */
    public static function getImageSize(string $filename): array|false
    {
        if (!Path::isLocal($filename)) {
            return false;
        }
        $realPath = realpath($filename);
        if ($realPath === false) {
            return false;
        }

        return @getimagesize($realPath);
    }
}
