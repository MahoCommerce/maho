<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Adminhtml_Blog_PostController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/blog/posts';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['save', 'delete', 'massDelete']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('cms/blog/posts');
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Blog Posts'));
        $this->_initAction();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $this->_title($this->__('Blog Post'));

        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('blog/post');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('blog')->__('This post no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getTitle() : $this->__('New Post'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('blog_post', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('blog')->__('Edit Post') : Mage::helper('blog')->__('New Post'),
                $id ? Mage::helper('blog')->__('Edit Post') : Mage::helper('blog')->__('New Post'),
            )
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $model = Mage::getModel('blog/post');
            $id = $this->getRequest()->getParam('id');

            if ($id) {
                $model->load($id);
            }

            try {
                $model->addData($data)
                    ->setUpdatedAt(Mage::getSingleton('core/date')->gmtDate())
                    ->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Post was successfully saved'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                    return;
                }
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('blog/post');
                $model->load($id);
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Post was successfully deleted'),
                );
                $this->_redirect('*/*/');
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', ['id' => $id]);
            }
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $postIds = $this->getRequest()->getParam('post');
        if (!is_array($postIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('blog')->__('Please select post(s)'),
            );
        } else {
            try {
                foreach ($postIds as $postId) {
                    $post = Mage::getModel('blog/post')->load($postId);
                    $post->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Total of %d post(s) were deleted', count($postIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('cms/blog/posts');
    }
}
