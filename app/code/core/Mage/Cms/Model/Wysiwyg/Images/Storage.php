<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use Maho\Storage\Mount;

/**
 * The media browser storage: directories, files and thumbnails on the media
 * mount below the "wysiwyg" root. Every path is a mount path. A folder is
 * read with one listing, so a remote mount answers one request per folder.
 */
class Mage_Cms_Model_Wysiwyg_Images_Storage extends \Maho\DataObject
{
    public const DIRECTORY_NAME_REGEXP = '/^[a-z0-9\-\_]+$/si';
    public const THUMBS_DIRECTORY_NAME = '.thumbs';
    public const THUMB_PLACEHOLDER_PATH_SUFFIX = 'images/wysiwyg/placeholder-image.svg';

    /**
     * Config object
     *
     * @var Mage_Core_Model_Config_Element
     */
    protected $_config;

    /**
     * Config object as array
     *
     * @var array|string
     */
    protected $_configAsArray;

    public function getMount(): Mount
    {
        return $this->getHelper()->getMount();
    }

    /**
     * The child directories of $path, as items with 'path' (the mount path)
     * and 'name' (the last segment). Hidden directories and the ones the
     * cms/browser/dirs config excludes are left out.
     */
    public function getDirsCollection(string $path): \Maho\Data\Collection
    {
        $conditions = ['reg_exp' => [], 'plain' => []];

        foreach ($this->getConfig()->dirs->exclude->children() as $dir) {
            $conditions[$dir->getAttribute('regexp') ? 'reg_exp' : 'plain'][(string) $dir] = true;
        }
        // "include" section takes precedence and can revoke directory exclusion
        foreach ($this->getConfig()->dirs->include->children() as $dir) {
            unset($conditions['reg_exp'][(string) $dir], $conditions['plain'][(string) $dir]);
        }

        $regExp = $conditions['reg_exp'] ? ('~' . implode('|', array_keys($conditions['reg_exp'])) . '~i') : null;
        $collection = new \Maho\Data\Collection();
        foreach ($this->listDirectory($path) as $item) {
            if (!$item->isDir()) {
                continue;
            }
            $name = basename($item->path());
            if (str_starts_with($name, '.')
                || array_key_exists($name, $conditions['plain'])
                || ($regExp && preg_match($regExp, $item->path()))
            ) {
                continue;
            }
            $collection->addItem(new \Maho\DataObject([
                'path' => $item->path(),
                'name' => $name,
                'id' => $this->getHelper()->convertPathToId($item->path()),
            ]));
        }

        return $collection;
    }

    /**
     * The files of $path, oldest first, as items with id, name, short_name,
     * url, thumb_url and, for an image on a local mount, width and height.
     *
     * @param string $type Type of storage, e.g. image, media etc.
     */
    public function getFilesCollection(string $path, ?string $type = null): \Maho\Data\Collection
    {
        $allowed = $this->getAllowedExtensions($type);
        $helper = $this->getHelper();
        $mount = $this->getMount();
        $localRoot = $mount->localRoot();

        $files = [];
        foreach ($this->listDirectory($path) as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $name = basename($item->path());
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($allowed && !in_array($extension, $allowed, true)) {
                continue;
            }
            $files[] = ['path' => $item->path(), 'name' => $name, 'mtime' => $item->lastModified() ?? 0];
        }
        usort($files, fn(array $a, array $b) => $a['mtime'] <=> $b['mtime'] ?: strcmp($a['name'], $b['name']));

        // One listing of the thumbnail directory replaces one existence check per file
        $thumbs = [];
        foreach ($this->listDirectory($this->thumbsDirectoryOf($path)) as $item) {
            if ($item->isFile()) {
                $thumbs[basename($item->path())] = true;
            }
        }

        $collection = new \Maho\Data\Collection();
        foreach ($files as $file) {
            $item = new \Maho\DataObject([
                'path' => $file['path'],
                'id' => $helper->idEncode($file['name']),
                'name' => $file['name'],
                'short_name' => $helper->getShortFilename($file['name']),
                'url' => $helper->getCurrentUrl() . $file['name'],
                'mtime' => $file['mtime'],
            ]);

            $thumbUrl = null;
            if ($this->isImage($file['name'])) {
                $thumbName = Mage_Core_Model_File_Uploader::getCorrectFileName($file['name']);
                if (isset($thumbs[$thumbName])) {
                    $thumbUrl = $this->getThumbnailUrl($path . '/' . $thumbName);
                } else {
                    $thumbUrl = Mage::getSingleton('adminhtml/url')->getUrl('*/*/thumbnail', [
                        'file' => $item->getId(),
                        'node' => $helper->convertPathToId($path),
                    ]);
                }

                if ($localRoot !== null) {
                    $size = @\Maho\Io::getImageSize($localRoot . '/' . $file['path']);
                    if (is_array($size)) {
                        $item->setWidth($size[0]);
                        $item->setHeight($size[1]);
                    }
                }
            }

            $item->setThumbUrl($thumbUrl ?: Mage::getDesign()->getSkinBaseUrl() . self::THUMB_PLACEHOLDER_PATH_SUFFIX);
            $collection->addItem($item);
        }

        return $collection;
    }

    /**
     * One shallow listing of $path. A directory that does not exist lists as empty.
     *
     * @return list<StorageAttributes>
     */
    protected function listDirectory(string $path): array
    {
        try {
            return $this->getMount()->listContents($path, false)->toArray();
        } catch (FilesystemException) {
            return [];
        }
    }

    /**
     * Normalizes a mount path and returns it only when it lies below the
     * storage root. The root itself passes only when $allowRoot is set.
     */
    protected function pathInRoot(string $path, bool $allowRoot = false): ?string
    {
        $root = $this->getHelper()->getStorageRootPath();
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === $root) {
            return $allowRoot ? $root : null;
        }
        if (!str_starts_with($path, $root . '/')) {
            return null;
        }

        return Mount::pathWithin($root, substr($path, strlen($root) + 1));
    }

    /** The thumbnail directory that mirrors $directory, a directory below the storage root. */
    protected function thumbsDirectoryOf(string $directory): string
    {
        $root = $this->getHelper()->getStorageRootPath();
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $thumbs = $this->getThumbnailRoot();
        if (str_starts_with($directory, $root . '/')) {
            $thumbs .= substr($directory, strlen($root));
        }

        return $thumbs;
    }

    /**
     * Create new directory in storage
     *
     * @param string $name New directory name
     * @param string $path Parent directory path
     * @throws Mage_Core_Exception
     * @return array New directory info
     */
    public function createDirectory($name, $path)
    {
        if (!preg_match(self::DIRECTORY_NAME_REGEXP, $name)) {
            Mage::throwException(Mage::helper('cms')->__('Invalid folder name. Please, use alphanumeric characters, underscores and dashes.'));
        }
        $mount = $this->getMount();
        $path = $this->pathInRoot((string) $path, true);
        if ($path === null || !$mount->directoryExists($path)) {
            $path = $this->getHelper()->getStorageRootPath();
        }

        $newPath = $path . '/' . $name;

        if ($mount->directoryExists($newPath) || $mount->fileExists($newPath)) {
            Mage::throwException(Mage::helper('cms')->__('A directory with the same name already exists. Please try another folder name.'));
        }

        try {
            $mount->createDirectory($newPath);
        } catch (FilesystemException) {
            Mage::throwException(Mage::helper('cms')->__('Cannot create new directory.'));
        }

        return [
            'name'          => $name,
            'short_name'    => $this->getHelper()->getShortFilename($name),
            'path'          => $newPath,
            'id'            => $this->getHelper()->convertPathToId($newPath),
        ];
    }

    /**
     * Recursively delete directory from storage
     *
     * @param string $path Target dir
     */
    public function deleteDirectory($path)
    {
        $root = $this->getHelper()->getStorageRootPath();
        if (trim(str_replace('\\', '/', (string) $path), '/') === $root) {
            Mage::throwException(Mage::helper('cms')->__('Cannot delete root directory %s.', $root));
        }
        $path = $this->pathInRoot((string) $path);
        if ($path === null) {
            throw new Exception('Detected malicious path or filename input.');
        }

        $mount = $this->getMount();
        try {
            $mount->deleteDirectory($path);
            $thumbs = $this->thumbsDirectoryOf($path);
            if ($mount->directoryExists($thumbs)) {
                $mount->deleteDirectory($thumbs);
            }
        } catch (FilesystemException) {
            Mage::throwException(Mage::helper('cms')->__('Cannot delete directory %s.', $path));
        }
    }

    /**
     * Delete file (and its thumbnail if exists) from storage
     *
     * @param string $target File path to be deleted
     * @return $this
     */
    public function deleteFile($target)
    {
        $mount = $this->getMount();
        $target = $this->pathInRoot((string) $target);
        if ($target === null) {
            throw new Exception('Detected malicious path or filename input.');
        }
        if ($mount->fileExists($target)) {
            $mount->delete($target);
        }

        $thumb = $this->getThumbnailPath($target, true);
        if ($thumb) {
            $mount->delete($thumb);
        }
        return $this;
    }

    /**
     * Upload and resize new file
     *
     * @param string $targetPath Target directory
     * @param string $type Type of storage, e.g. image, media etc.
     * @return array
     * @throws Mage_Core_Exception
     */
    public function uploadFile($targetPath, $type = null)
    {
        $uploader = Mage::getModel('core/file_uploader', 'image');
        if ($allowed = $this->getAllowedExtensions($type)) {
            $uploader->setAllowedExtensions($allowed);
        }
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        if ($type == 'image') {
            $uploader->addValidateCallback(
                Mage_Core_Model_File_Validator_Image::NAME,
                Mage::getModel('core/file_validator_image'),
                'validate',
            );
        }
        $result = $uploader->saveToStorage($this->getMount(), $targetPath);

        if (!$result) {
            Mage::throwException(Mage::helper('cms')->__('Cannot upload file.'));
        }

        // create thumbnail
        if ($type == 'image') {
            $this->resizeFile($result['path'] . '/' . $uploader->getUploadedFileName(), true);
        }

        return $result;
    }

    /**
     * Thumbnail path getter
     *
     * @param  string $filePath original file path
     * @param bool $checkFile OPTIONAL is it necessary to check file availability
     * @return string | false
     */
    public function getThumbnailPath($filePath, $checkFile = false)
    {
        $root = $this->getHelper()->getStorageRootPath();
        $filePath = trim(str_replace('\\', '/', (string) $filePath), '/');

        if (str_starts_with($filePath, $root . '/')) {
            $thumbPath = $this->getThumbnailRoot() . substr($filePath, strlen($root));

            if (!$checkFile || $this->getMount()->fileExists($thumbPath)) {
                return $thumbPath;
            }
        }

        return false;
    }

    /**
     * Thumbnail URL getter
     *
     * @param  string $filePath original file path
     * @param bool $checkFile OPTIONAL is it necessary to check file availability
     * @return string|false
     */
    public function getThumbnailUrl($filePath, $checkFile = false)
    {
        $thumbPath = $this->getThumbnailPath($filePath, $checkFile);
        if ($thumbPath === false) {
            return false;
        }

        return $this->getMount()->publicUrl($thumbPath) . '?rand=' . time();
    }

    /**
     * Create thumbnail for image and save it to thumbnails directory
     *
     * @param string $source Image path to be resized
     * @param bool $keepRation Keep aspect ratio or not
     * @return bool|string Resized filepath or false if errors were occurred
     */
    public function resizeFile($source, $keepRation = true)
    {
        $mount = $this->getMount();
        $source = $this->pathInRoot((string) $source);
        if ($source === null || !$mount->fileExists($source)) {
            return false;
        }

        $width = $this->getConfigData('resize_width');
        $height = $this->getConfigData('resize_height');
        if ($width == 0 || $height == 0) {
            return false;
        }

        $dest = $this->getThumbsPath($source) . '/' . Mage_Core_Model_File_Uploader::getCorrectFileName(basename($source));
        try {
            $image = Maho::getImageManager()->decodeBinary($mount->read($source));
            if ($width && $height) {
                $image->containDown($width, $height);
            } else {
                $image->scale($width, $height);
            }
            $mount->write($dest, (string) $image->encodeUsingPath($dest));
        } catch (\Throwable $e) {
            Mage::logException($e);
            return false;
        }

        return $dest;
    }

    /**
     * Resize images on the fly in controller action
     *
     * @param string $filename File basename
     * @return bool|string Thumbnail path or false for errors
     */
    public function resizeOnTheFly($filename)
    {
        $filename = (string) $filename;
        if ($filename === '' || basename(str_replace('\\', '/', $filename)) !== $filename) {
            return false;
        }
        $source = Mount::pathWithin($this->getHelper()->getCurrentPath(), $filename);
        if ($source === null) {
            return false;
        }
        return $this->resizeFile($source);
    }

    /**
     * The thumbnail directory of a file, or the thumbnail root
     *
     * @param false|string $filePath Path of the file on the media mount
     * @return string
     */
    public function getThumbsPath($filePath = false)
    {
        if (!$filePath) {
            return $this->getThumbnailRoot();
        }

        return $this->thumbsDirectoryOf(dirname(str_replace('\\', '/', (string) $filePath)));
    }

    /**
     * Media Storage Helper getter
     * @return Mage_Cms_Helper_Wysiwyg_Images
     */
    public function getHelper()
    {
        return Mage::helper('cms/wysiwyg_images');
    }

    /**
     * Storage session
     *
     * @return Mage_Adminhtml_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Config object getter
     *
     * @return Mage_Core_Model_Config_Element
     */
    public function getConfig()
    {
        if (!$this->_config) {
            $this->_config = Mage::getConfig()->getNode('cms/browser', 'adminhtml');
        }

        return $this->_config;
    }

    /**
     * Config object as array getter
     *
     * @return array|string
     */
    public function getConfigAsArray()
    {
        if (!$this->_configAsArray) {
            $this->_configAsArray = $this->getConfig()->asCanonicalArray();
        }

        return $this->_configAsArray;
    }

    /**
     * Wysiwyg Config reader
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigData($key, $default = false)
    {
        $configArray = $this->getConfigAsArray();
        $key = (string) $key;

        return array_key_exists($key, $configArray) ? $configArray[$key] : $default;
    }

    /**
     * Prepare allowed_extensions config settings
     *
     * @param string $type Type of storage, e.g. image, media etc.
     * @return array Array of allowed file extensions
     */
    public function getAllowedExtensions($type = null)
    {
        $extensions = $this->getConfigData('extensions');

        if (is_string($type) && array_key_exists("{$type}_allowed", $extensions)) {
            $allowed = $extensions["{$type}_allowed"];
        } else {
            $allowed = $extensions['allowed'];
        }

        return array_keys(array_filter($allowed));
    }

    /** The thumbnail root on the media mount. */
    public function getThumbnailRoot(): string
    {
        return $this->getHelper()->getStorageRootPath() . '/' . self::THUMBS_DIRECTORY_NAME;
    }

    /**
     * Simple way to check whether file is image or not based on extension
     *
     * @param string $filename
     * @return bool
     */
    public function isImage($filename)
    {
        if (!$this->hasData('_image_extensions')) {
            $this->setData('_image_extensions', $this->getAllowedExtensions('image'));
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $this->_getData('_image_extensions'));
    }
}
