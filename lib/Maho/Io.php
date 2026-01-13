<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho;

/**
 * Abstract I/O class with security utilities for path validation
 */
abstract class Io
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
    public function open(array $args = [])
    {
        return false;
    }

    /**
     * @return string
     */
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
     * @deprecated Use \Maho\Io::validatePath() instead for secure path validation
     * @param string $haystackPath
     * @param string $needlePath
     * @return bool
     */
    public function allowedPath($haystackPath, $needlePath)
    {
        return self::validatePath($haystackPath, $needlePath) !== false;
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
     * Check if a path contains stream wrappers (phar://, http://, etc.)
     *
     * Stream wrappers like phar:// can trigger deserialization when used with
     * functions like getimagesize(), file_exists(), is_readable(), etc.
     */
    public static function hasStreamWrapper(string $path): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path);
    }

    /**
     * Validate and resolve a file path securely
     *
     * This method provides comprehensive path validation:
     * 1. Blocks stream wrappers (phar://, http://, etc.) to prevent deserialization attacks
     * 2. Resolves the path using realpath() to handle symlinks and relative paths
     * 3. Optionally validates that the path stays within an allowed base directory
     *
     * @param string $path The file path to validate
     * @param string|null $allowedBaseDir Optional base directory the path must be within
     * @return string|false The validated real path, or false if validation fails
     */
    public static function validatePath(string $path, ?string $allowedBaseDir = null): string|false
    {
        // Block stream wrappers (phar://, http://, etc.)
        if (self::hasStreamWrapper($path)) {
            return false;
        }

        // Resolve to real path (handles symlinks, .., etc.)
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        // If base directory specified, ensure path stays within it
        if ($allowedBaseDir !== null) {
            // Also block stream wrappers in base dir
            if (self::hasStreamWrapper($allowedBaseDir)) {
                return false;
            }

            $realBaseDir = realpath($allowedBaseDir);
            if ($realBaseDir === false) {
                return false;
            }

            // Path must start with base directory + separator (or be exactly the base dir)
            if ($realPath !== $realBaseDir && !str_starts_with($realPath, $realBaseDir . DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        return $realPath;
    }

    /**
     * Safe wrapper for getimagesize() that prevents phar:// deserialization
     *
     * @param string $filename The file path to check
     * @return array<int|string, mixed>|false Image size info or false on failure/unsafe path
     */
    public static function getImageSize(string $filename): array|false
    {
        $safePath = self::validatePath($filename);
        if ($safePath === false) {
            return false;
        }

        return @getimagesize($safePath);
    }
}
