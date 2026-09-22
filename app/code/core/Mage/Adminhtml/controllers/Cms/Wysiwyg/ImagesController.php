<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Cms_Wysiwyg_ImagesController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/media_gallery';

    /**
     * Init storage
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->getStorage();
        return $this;
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/index')]
    public function indexAction()
    {
        if ($this->getRequest()->isAjax()) {
            return $this->_forward('popup');
        }

        $storeId = (int) $this->getRequest()->getParam('store');

        try {
            Mage::helper('cms/wysiwyg_images')->getCurrentPath();
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_initAction()
            ->_title($this->__('CMS'))
            ->_title($this->__('Media Library'));

        $this->loadLayout();

        $block = $this->getLayout()->getBlock('wysiwyg_images.js');
        if ($block) {
            $block->setStoreId($storeId)
                ->setCanInsertImage(false);
        }

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/popup')]
    public function popupAction(): void
    {
        $storeId = (int) $this->getRequest()->getParam('store');

        try {
            Mage::helper('cms/wysiwyg_images')->getCurrentPath();
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_initAction();
        $this->loadLayout('overlay_popup');

        $block = $this->getLayout()->getBlock('wysiwyg_images.js');
        if ($block) {
            $block->setStoreId($storeId)
                ->setCanInsertImage(true);
        }

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/treeJson')]
    public function treeJsonAction(): void
    {
        try {
            $this->_initAction();
            $path = Mage::helper('cms/wysiwyg_images')->getCurrentPath();
            $block = $this->getLayout()->createBlock('adminhtml/cms_wysiwyg_images_tree');
            $this->getResponse()->setBodyJson($block->getTreeJson($path));
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/contents')]
    public function contentsAction(): void
    {
        try {
            $this->_initAction();
            $this->loadLayout('empty');
            $this->renderLayout();
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/newFolder')]
    public function newFolderAction(): void
    {
        try {
            $this->_initAction();
            $name = $this->getRequest()->getPost('name');
            $path = Mage::helper('cms/wysiwyg_images')->getCurrentPath();
            $result = $this->getStorage()->createDirectory($name, $path);
            $this->getResponse()->setBodyJson($result);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    #[Maho\Config\Route('/admin/cms_wysiwyg_images/deleteFolder')]
    public function deleteFolderAction(): void
    {
        try {
            $path = Mage::helper('cms/wysiwyg_images')->getCurrentPath();
            $this->getStorage()->deleteDirectory($path);
            $this->getResponse()->setBodyJson([]);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete file from media storage
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/deleteFiles')]
    public function deleteFilesAction(): void
    {
        try {
            if (!$this->getRequest()->isPost()) {
                throw new Exception('Wrong request.');
            }
            $files = Mage::helper('core')->jsonDecode($this->getRequest()->getParam('files'));

            /** @var Mage_Cms_Helper_Wysiwyg_Images $helper */
            $helper = Mage::helper('cms/wysiwyg_images');
            $path = $helper->getCurrentPath();
            foreach ($files as $file) {
                $filePath = $this->_resolveFileInCurrentPath($helper->idDecode($file), $path);
                if ($filePath !== null) {
                    $this->getStorage()->deleteFile($filePath);
                }
            }
            $this->getResponse()->setBodyJson([]);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Files upload processing
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/upload')]
    public function uploadAction(): void
    {
        try {
            $this->_initAction();
            $targetPath = Mage::helper('cms/wysiwyg_images')->getCurrentPath();
            $result = $this->getStorage()->uploadFile($targetPath, $this->getRequest()->getParam('type'));
            $this->getResponse()->setBodyJson($result);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Fire when select image
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/onInsert')]
    public function onInsertAction(): void
    {
        $helper = Mage::helper('cms/wysiwyg_images');
        $storeId = $this->getRequest()->getParam('store');

        $filename = $this->getRequest()->getParam('filename');
        $filename = $helper->idDecode($filename);

        $alt = $this->getRequest()->getParam('alt');

        Mage::helper('catalog')->setStoreId($storeId);
        $helper->setStoreId($storeId);

        $image = $helper->getImageHtmlDeclaration($filename, $alt);
        $this->getResponse()->setBody($image);
    }

    /**
     * Generate image thumbnail on the fly
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/thumbnail')]
    public function thumbnailAction(): void
    {
        try {
            $file = $this->getRequest()->getParam('file');
            $file = Mage::helper('cms/wysiwyg_images')->idDecode($file);

            $thumb = $this->getStorage()->resizeOnTheFly($file);
            if ($thumb === false) {
                Mage::throwException('Thumbnail image could not be generated');
            }

            $mount = Mage::getStorage('media');
            $image = $mount->read($thumb);

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-type', $mount->mimeType($thumb), true);

        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()
                ->setHttpResponseCode(500);
        }

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        if (isset($image)) {
            print $image;
        }
        exit(0);
    }

    /**
     * Get image URL for editing
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/getImageUrl')]
    public function getImageUrlAction(): void
    {
        try {
            // Validate CSRF token for POST requests
            if ($this->getRequest()->isPost() && !$this->_validateFormKey()) {
                throw new Exception('Invalid form key. Please refresh the page and try again.');
            }

            $fileId = $this->getRequest()->getParam('file_id');
            $fileId = Mage::helper('cms/wysiwyg_images')->idDecode($fileId);

            if (!$fileId) {
                throw new Exception('File ID is required.');
            }

            /** @var Mage_Cms_Helper_Wysiwyg_Images $helper */
            $helper = Mage::helper('cms/wysiwyg_images');
            $filePath = $this->_resolveFileInCurrentPath($fileId, $helper->getCurrentPath());
            if ($filePath === null) {
                throw new Exception('File not found.');
            }

            $this->getResponse()->setBodyJson([
                'success' => true,
                'url' => Mage::getStorage('media')->publicUrl($filePath),
            ]);

        } catch (Exception $e) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save edited image from image editor
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg_images/editImage')]
    public function editImageAction(): void
    {
        try {
            if (!$this->getRequest()->isPost()) {
                throw new Exception('Wrong request method.');
            }

            // Validate CSRF token
            if (!$this->_validateFormKey()) {
                throw new Exception('Invalid form key. Please refresh the page and try again.');
            }

            $fileId = $this->getRequest()->getParam('file_id');
            $fileId = Mage::helper('cms/wysiwyg_images')->idDecode($fileId);

            if (!$fileId) {
                throw new Exception('File ID is required.');
            }

            // Check if edited image file was uploaded
            if (!isset($_FILES['edited_image']) || $_FILES['edited_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No edited image provided.');
            }

            /** @var Mage_Cms_Helper_Wysiwyg_Images $helper */
            $helper = Mage::helper('cms/wysiwyg_images');
            $currentPath = $helper->getCurrentPath();
            $mount = Mage::getStorage('media');

            $originalFilePath = $this->_resolveFileInCurrentPath($fileId, $currentPath);
            if ($originalFilePath === null) {
                throw new Exception('Original file not found.');
            }

            // Get new filename from request or use original
            $newFilename = $this->getRequest()->getParam('new_filename');
            $originalPathInfo = pathinfo($originalFilePath);

            // The editor always writes the configured image type, whatever the original extension
            $configuredExtension = ltrim(Maho::getConfiguredImageExtension(), '.');

            if ($newFilename) {
                $baseFilename = pathinfo($newFilename, PATHINFO_FILENAME);
                $targetFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($baseFilename . '.' . $configuredExtension);
                if ($baseFilename !== $originalPathInfo['filename'] && $mount->fileExists($currentPath . '/' . $targetFilename)) {
                    throw new Exception('A file with this name already exists.');
                }
            } else {
                $targetFilename = $originalPathInfo['filename'] . '.' . $configuredExtension;
            }

            if (!\Maho\Io::getImageSize($_FILES['edited_image']['tmp_name'])) {
                throw new Exception('Uploaded file is not a valid image.');
            }

            $uploader = Mage::getModel('core/file_uploader', 'edited_image');
            $uploader->setAllowRenameFiles(false);
            $uploader->setFilesDispersion(false);
            if (!$uploader->saveToStorage($mount, $currentPath, $targetFilename)) {
                throw new Exception('Failed to save edited image.');
            }

            // Clear any cached thumbnails by regenerating
            $this->getStorage()->resizeOnTheFly($targetFilename);

            $this->getResponse()->setBodyJson([
                'success' => true,
                'message' => 'Image edited successfully',
            ]);

        } catch (Exception $e) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The mount path of a file name inside $currentPath, or null when the name
     * holds a directory part, leaves the directory, or names no file.
     */
    protected function _resolveFileInCurrentPath(string|false $fileName, string $currentPath): ?string
    {
        if ($fileName === false || $fileName === '' || basename(str_replace('\\', '/', $fileName)) !== $fileName) {
            return null;
        }
        $filePath = \Maho\Storage\Mount::pathWithin($currentPath, $fileName);
        if ($filePath === null || !Mage::getStorage('media')->fileExists($filePath)) {
            return null;
        }

        return $filePath;
    }

    /**
     * Register storage model and return it
     *
     * @return Mage_Cms_Model_Wysiwyg_Images_Storage
     */
    public function getStorage()
    {
        if (!Mage::registry('storage')) {
            $storage = Mage::getModel('cms/wysiwyg_images_storage');
            Mage::register('storage', $storage);
        }
        return Mage::registry('storage');
    }

    /**
     * Save current path in session
     *
     * @return $this
     */
    #[\Deprecated(message: 'since 25.7.0 current path is no longer stored in session')]
    protected function _saveSessionCurrentPath()
    {
        return $this;
    }
}
