<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

use Maho\Storage\Mount;

/**
 * Paths of the media browser. Every path is a directory on the media mount,
 * such as "wysiwyg" or "wysiwyg/banners", never a local directory.
 */
class Mage_Cms_Helper_Wysiwyg_Images extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Cms';

    /**
     * Current directory path
     * @var string|null
     */
    protected $_currentPath;

    /**
     * Current directory URL
     * @var string
     */
    protected $_currentUrl;

    /**
     * Currently selected store ID if applicable
     *
     * @var int
     */
    protected $_storeId = null;

    /**
     * Set a specified store ID value
     *
     * @param int $store
     * @return $this
     */
    public function setStoreId($store)
    {
        $this->_storeId = $store;
        return $this;
    }

    public function getMount(): Mount
    {
        return Mage::getStorage('media');
    }

    /** The root directory of the media browser on the media mount. */
    public function getStorageRootPath(): string
    {
        return Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY;
    }

    /**
     * Images Storage base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return Mage::getBaseUrl('media');
    }

    /**
     * Ext Tree node key name
     *
     * @return string
     */
    public function getTreeNodeName()
    {
        return 'node';
    }

    /**
     * Encode a directory path to an HTML element id. The id holds the path
     * below the storage root with a leading slash, so it stays stable when
     * the mount moves.
     */
    public function convertPathToId(string $path): string
    {
        $root = $this->getStorageRootPath();
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === $root) {
            return $this->idEncode('');
        }
        if (str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root));
        }
        return $this->idEncode('/' . ltrim($path, '/'));
    }

    /**
     * Decode an HTML element id to a directory path on the media mount,
     * or null when the id leaves the storage root.
     */
    public function convertIdToPath(string $id): ?string
    {
        $relative = $this->idDecode($id);
        if ($relative === false) {
            return null;
        }
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return $this->getStorageRootPath();
        }
        return \Maho\Io::getPathWithinMount($this->getMount(), $this->getStorageRootPath(), $relative);
    }

    /**
     * File system path correction
     *
     * @param string $path Original path
     * @param bool $trim Trim slashes or not
     * @return string
     */
    public function correctPath($path, $trim = true)
    {
        $path = strtr($path, "\\\/", DS . DS);
        if ($trim) {
            $path = trim($path, DS);
        }
        return $path;
    }

    /**
     * Returns the mount path of the requested folder below the storage root,
     * or null when the folder is outside the root. Each dot segment is removed first.
     */
    public function resolveFolder(string $folder): ?string
    {
        $root = $this->getStorageRootPath();
        $segments = array_filter(
            explode('/', str_replace('\\', '/', $this->correctPath($folder))),
            static fn(string $segment): bool => $segment !== '..' && $segment !== '.' && $segment !== '',
        );
        $subFolder = preg_replace('#^' . preg_quote($root, '#') . '(/|$)#', '', implode('/', $segments));
        if ($subFolder === '' || $subFolder === null) {
            return $root;
        }

        return \Maho\Io::getPathWithinMount($this->getMount(), $root, $subFolder);
    }

    /**
     * Return file system path as Url string
     *
     * @param string $path
     * @return string
     */
    public function convertPathToUrl($path)
    {
        return str_replace(DS, '/', $path);
    }

    /**
     * Check whether using static URLs is allowed
     *
     * @return bool
     */
    public function isUsingStaticUrlsAllowed()
    {
        $checkResult = new stdClass();
        $checkResult->isAllowed = false;
        Mage::dispatchEvent('cms_wysiwyg_images_static_urls_allowed', [
            'result'   => $checkResult,
            'store_id' => $this->_storeId,
        ]);
        return $checkResult->isAllowed;
    }

    /**
     * Prepare Image insertion declaration for Wysiwyg or textarea(as_is mode)
     *
     * @param string $filename Filename transferred via Ajax
     * @param string $alt Alt text for the image
     * @return string
     */
    public function getImageHtmlDeclaration($filename, $alt = '')
    {
        $fileurl = $this->getCurrentUrl() . $filename;
        $mediaPath = $this->getCurrentPath() . '/' . $filename;
        $directive = sprintf('{{media url="%s"}}', $mediaPath);
        $html = sprintf(
            '<img src="%s" alt="%s">',
            $this->isUsingStaticUrlsAllowed() ? $fileurl : $directive,
            $this->escapeHtml(is_string($alt) ? $alt : ''),
        );
        return $html;
    }

    /**
     * The selected directory on the media mount, or the storage root when
     * the request names none or names one that does not exist. The root is
     * created on first use.
     *
     * @throws Mage_Core_Exception
     */
    public function getCurrentPath(): string
    {
        if ($this->_currentPath === null) {
            $mount = $this->getMount();
            $currentPath = $this->getStorageRootPath();
            $node = $this->_getRequest()->getParam($this->getTreeNodeName());
            if ($node) {
                $path = $this->convertIdToPath((string) $node);
                if ($path !== null && $mount->directoryExists($path)) {
                    $currentPath = $path;
                }
            }
            try {
                if (!$mount->directoryExists($currentPath)) {
                    $mount->createDirectory($currentPath);
                }
            } catch (\League\Flysystem\FilesystemException) {
                Mage::throwException(Mage::helper('cms')->__('The directory %s is not writable by server.', $currentPath));
            }
            $this->_currentPath = $currentPath;
        }
        return $this->_currentPath;
    }

    /**
     * Return URL based on current selected directory or root directory for startup
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        if (!$this->_currentUrl) {
            $this->_currentUrl = Mage::app()->getStore($this->_storeId)->getBaseUrl('media') . $this->getCurrentPath() . '/';
        }
        return $this->_currentUrl;
    }

    /**
     * Storage model singleton
     *
     * @return Mage_Cms_Model_Wysiwyg_Images_Storage
     */
    public function getStorage()
    {
        return Mage::getSingleton('cms/wysiwyg_images_storage');
    }

    /**
     * Encode string to valid HTML id element, based on base64 encoding
     *
     * @param string $string
     * @return string
     */
    public function idEncode($string)
    {
        return strtr(base64_encode($string), '+/=', ':_-');
    }

    /**
     * Revert operation to idEncode
     *
     * @param string $string
     * @return string|false
     */
    public function idDecode($string)
    {
        $string = strtr($string, ':_-', '+/=');
        return base64_decode($string);
    }

    /**
     * Reduce filename by replacing some characters with dots
     *
     * @param string $filename
     * @param int $maxLength Maximum filename
     * @return string Truncated filename
     */
    public function getShortFilename($filename, $maxLength = 20)
    {
        if (strlen($filename) <= $maxLength) {
            return $filename;
        }
        return substr($filename, 0, $maxLength) . '...';
    }
}
