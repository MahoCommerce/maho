<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Cms_MedialibraryController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/media_gallery';

    protected function _initAction(): self
    {
        $this->getStorage();
        $this->loadLayout()
            ->_setActiveMenu('cms/media_library')
            ->_addBreadcrumb(Mage::helper('cms')->__('CMS'), Mage::helper('cms')->__('CMS'))
            ->_addBreadcrumb(Mage::helper('cms')->__('Media Library'), Mage::helper('cms')->__('Media Library'));
        return $this;
    }

    public function indexAction(): void
    {
        try {
            Mage::helper('cms/wysiwyg_images')->getCurrentPath();
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_initAction()
            ->_title($this->__('CMS'))
            ->_title($this->__('Media Library'));

        $this->renderLayout();
    }

    public function treeJsonAction(): void
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

    public function contentsAction(): void
    {
        try {
            $this->_initAction()->_saveSessionCurrentPath();

            // Create and render the files block directly
            $filesBlock = $this->getLayout()->createBlock('adminhtml/cms_wysiwyg_images_content_files');
            $filesBlock->setTemplate('cms/browser/content/files.phtml');

            $this->getResponse()->setBody($filesBlock->toHtml());
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function newFolderAction(): void
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

    public function deleteFolderAction(): void
    {
        try {
            $path = $this->getStorage()->getSession()->getCurrentPath();
            $this->getStorage()->deleteDirectory($path);
            $this->getResponse()->setBodyJson([]);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function deleteFilesAction(): void
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

    public function uploadAction(): void
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

    public function thumbnailAction(): void
    {
        $file = $this->getRequest()->getParam('file');
        $file = Mage::helper('cms/wysiwyg_images')->idDecode($file);
        $thumb = $this->getStorage()->resizeOnTheFly($file);
        if ($thumb !== false) {
            $image = Maho::getImageManager()->read($thumb);
            $imageInfo = @getimagesize($thumb);
        } else {
            $image = Maho::getImageManager()->read(Mage::getSingleton('cms/wysiwyg_config')->getSkinImagePlaceholderPath());
            $imageInfo = @getimagesize(Mage::getSingleton('cms/wysiwyg_config')->getSkinImagePlaceholderPath());
        }

        $this->getResponse()->setHeader('Content-type', $imageInfo['mime']);
        $this->getResponse()->setBody($image->encode());
    }

    public function getStorage(): Mage_Cms_Model_Wysiwyg_Images_Storage
    {
        if (!Mage::registry('storage')) {
            $storage = Mage::getModel('cms/wysiwyg_images_storage');
            Mage::register('storage', $storage);
        }
        return Mage::registry('storage');
    }

    protected function _saveSessionCurrentPath(): self
    {
        if ($this->getRequest()->isPost()) {
            $this->getStorage()
                ->getSession()
                ->setCurrentPath(Mage::helper('cms/wysiwyg_images')->getCurrentPath());
        }
        return $this;
    }
}
