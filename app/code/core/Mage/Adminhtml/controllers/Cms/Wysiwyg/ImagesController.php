<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
                $file = $helper->idDecode($file);
                $filePath = realpath($path . DS . $file);
                if (str_starts_with($filePath, realpath($path)) &&
                    str_starts_with($filePath, realpath($helper->getStorageRoot()))
                ) {
                    $this->getStorage()->deleteFile($path . DS . $file);
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
    public function thumbnailAction(): void
    {
        try {
            $file = $this->getRequest()->getParam('file');
            $file = Mage::helper('cms/wysiwyg_images')->idDecode($file);

            $thumb = $this->getStorage()->resizeOnTheFly($file);
            if ($thumb === false) {
                Mage::throwException('Thumbnail image could not be generated');
            }

            $image = Maho::getImageManager()->read($thumb)->encode();

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-type', $image->mediaType(), true);

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
            $currentPath = $helper->getCurrentPath();

            // Get file path
            $filePath = $currentPath . DS . $fileId;

            // Validate file exists and is within allowed path
            if (!file_exists($filePath)) {
                throw new Exception('File not found.');
            }

            if (!str_starts_with(realpath($filePath), realpath($helper->getStorageRoot()))) {
                throw new Exception('Invalid file path.');
            }

            // Construct URL
            $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
            $relativePath = str_replace($helper->getStorageRoot(), '', $filePath);
            $imageUrl = $mediaUrl . 'wysiwyg' . str_replace(DS, '/', $relativePath);

            $this->getResponse()->setBodyJson([
                'success' => true,
                'url' => $imageUrl,
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

            // Get original file path
            $originalFilePath = $currentPath . DS . $fileId;

            // Validate file exists and is within allowed path
            if (!file_exists($originalFilePath)) {
                throw new Exception('Original file not found.');
            }

            if (!str_starts_with(realpath($originalFilePath), realpath($helper->getStorageRoot()))) {
                throw new Exception('Invalid file path.');
            }

            // Get new filename from request or use original
            $newFilename = $this->getRequest()->getParam('new_filename');
            $originalPathInfo = pathinfo($originalFilePath);

            // Get configured image file type and extension
            $configuredType = (int) Mage::getStoreConfig('system/media_storage_configuration/image_file_type');
            $configuredExtension = match ($configuredType) {
                IMAGETYPE_AVIF => 'avif',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                default        => 'webp',
            };

            if ($newFilename) {
                // Always replace extension with configured type
                // Handle cases where user typed filename with extension and editor added another extension

                // Extract base filename without any extensions
                $baseFilename = pathinfo($newFilename, PATHINFO_FILENAME);

                $newFilename = $baseFilename . '.' . $configuredExtension;

                // Clean filename
                $newFilename = Mage_Core_Model_File_Uploader::getCorrectFileName($newFilename);

                // Determine if it's the same as original (ignoring extension)
                $originalBasename = $originalPathInfo['filename'];

                if ($baseFilename === $originalBasename) {
                    // Same base name - replace with new extension if different
                    if ($configuredExtension !== $originalPathInfo['extension']) {
                        // Different extension - create new file with new extension
                        $targetPath = $currentPath . DS . $newFilename;
                    } else {
                        // Same extension - replace original
                        $targetPath = $originalFilePath;
                    }
                } else {
                    // Different base name - save as new file
                    $targetPath = $currentPath . DS . $newFilename;

                    // Check if new filename already exists
                    if (file_exists($targetPath)) {
                        throw new Exception('A file with this name already exists.');
                    }
                }
            } else {
                // No filename provided - use original name with configured extension
                if ($configuredExtension !== $originalPathInfo['extension']) {
                    // Different extension - create new file with new extension
                    $newFilename = $originalPathInfo['filename'] . '.' . $configuredExtension;
                    $targetPath = $currentPath . DS . $newFilename;
                } else {
                    // Same extension - replace original
                    $targetPath = $originalFilePath;
                }
            }

            // Move uploaded edited image
            $uploadedFile = $_FILES['edited_image']['tmp_name'];

            // Validate uploaded file is an image
            if (!\Maho\Io::getImageSize($uploadedFile)) {
                throw new Exception('Uploaded file is not a valid image.');
            }

            if (!move_uploaded_file($uploadedFile, $targetPath)) {
                throw new Exception('Failed to save edited image.');
            }

            // Clear any cached thumbnails by regenerating
            $this->getStorage()->resizeOnTheFly($fileId);

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
     * @deprecated since 25.7.0 current path is no longer stored in session
     */
    protected function _saveSessionCurrentPath()
    {
        return $this;
    }
}
