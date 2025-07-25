<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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

    public function treeJsonAction()
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

    public function contentsAction()
    {
        try {
            $this->_initAction();
            $this->loadLayout('empty');
            $this->renderLayout();
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function newFolderAction()
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

    public function deleteFolderAction()
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
    public function deleteFilesAction()
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
    public function uploadAction()
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
    public function onInsertAction()
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
    public function thumbnailAction()
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

            // Get original file info
            $pathInfo = pathinfo($originalFilePath);
            $originalExtension = strtolower($pathInfo['extension']);

            // Create backup of original (optional)
            $backupPath = $pathInfo['dirname'] . DS . $pathInfo['filename'] . '_backup_' . time() . '.' . $originalExtension;
            copy($originalFilePath, $backupPath);

            // Move uploaded edited image to replace original
            $uploadedFile = $_FILES['edited_image']['tmp_name'];

            // Validate uploaded file is an image
            $imageInfo = getimagesize($uploadedFile);
            if (!$imageInfo) {
                throw new Exception('Uploaded file is not a valid image.');
            }

            // Keep original extension format if possible
            $targetPath = $originalFilePath;
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
