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

namespace Maho\Io;

abstract class AbstractIo implements IoInterface
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
     * @return AbstractIo
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

    #[\Override]
    public function dirsep()
    {
        return '/';
    }

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
            } elseif ($pathParts[$i] == '..') {
                if ((isset($realPathParts[0])  &&  $realPathParts[0] != '..') || ($pathTokR != '')) {
                    array_pop($realPathParts);
                    continue;
                }
            }

            $realPathParts[] = $pathParts[$i];
        }

        return $pathTokR . implode('/', $realPathParts);
    }

    public function allowedPath($haystackPath, $needlePath)
    {
        return str_starts_with($this->getCleanPath($haystackPath), $this->getCleanPath($needlePath));
    }

    /**
     * Replace full path to relative
     *
     * @param $path
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
}
