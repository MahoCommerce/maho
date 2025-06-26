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
        $storeId = (int) $this->getRequest()->getParam('store');

        try {
            Mage::helper('cms/wysiwyg_images')->getCurrentPath();
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_initAction()
            ->_title($this->__('CMS'))
            ->_title($this->__('Media Library'));

        if ($this->getRequest()->isAjax()) {
            $canInsertImage = true;
            $this->loadLayout('overlay_popup');
        } else {
            $canInsertImage = false;
            $this->loadLayout();
        }

        $block = $this->getLayout()->getBlock('wysiwyg_images.js');
        if ($block) {
            $block->setStoreId($storeId)
                ->setCanInsertImage($canInsertImage);
        }

        $this->renderLayout();
    }

    public function treeJsonAction()
    {
        try {
            $this->_initAction();
            $this->getResponse()->setBodyJson(
                $this->getLayout()->createBlock('adminhtml/cms_wysiwyg_images_tree')->getTreeJson(),
            );
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function contentsAction()
    {
        try {
            $this->_initAction()->_saveSessionCurrentPath();
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
            $path = $this->getStorage()->getSession()->getCurrentPath();
            $result = $this->getStorage()->createDirectory($name, $path);
            $this->getResponse()->setBodyJson($result);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function deleteFolderAction()
    {
        try {
            $path = $this->getStorage()->getSession()->getCurrentPath();
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
            $path = $this->getStorage()->getSession()->getCurrentPath();
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
            $targetPath = $this->getStorage()->getSession()->getCurrentPath();
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
    protected function _saveSessionCurrentPath()
    {
        if ($this->getRequest()->isPost()) {
            $this->getStorage()
                ->getSession()
                ->setCurrentPath(Mage::helper('cms/wysiwyg_images')->getCurrentPath());
        }
        return $this;
    }
}
