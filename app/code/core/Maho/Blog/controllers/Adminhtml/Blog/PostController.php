<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_Adminhtml_Blog_PostController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/blog/posts';

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('cms/blog/posts');
        return $this;
    }

    #[Maho\Config\Route('/admin/blog_post/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Blog Posts'));
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/blog_post/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/blog_post/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Blog Post'));

        $id = (int) $this->getRequest()->getParam('id');
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $model = $id ? $this->getService()->getById($id) : $this->getService()->newPost();
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $this->_title($model->getId() ? $model->getTitle() : $this->__('New Post'));

        $data = $session->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('blog_post', $model);

        $label = $id ? Mage::helper('blog')->__('Edit Post') : Mage::helper('blog')->__('New Post');
        $this->_initAction()->_addBreadcrumb($label, $label)->renderLayout();
    }

    #[Maho\Config\Route('/admin/blog_post/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $model = $id ? $this->getService()->getById($id) : $this->getService()->newPost();
            $this->getService()->save($model->addData($data));
            $session->addSuccess(Mage::helper('blog')->__('Post was successfully saved'));
            $session->setFormData(false);
            $this->getRequest()->getParam('back') ? $this->_redirect('*/*/edit', ['id' => $model->getId()]) : $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('blog')->__('Unable to save the post.'));
        }
        $session->setFormData($data);
        $this->_redirect('*/*/edit', ['id' => $id]);
    }

    #[Maho\Config\Route('/admin/blog_post/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $this->getService()->delete($this->getService()->getById($id));
            $session->addSuccess(Mage::helper('blog')->__('Post was successfully deleted'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/blog_post/massDelete')]
    public function massDeleteAction(): void
    {
        $postIds = $this->getRequest()->getParam('post');
        $session = Mage::getSingleton('adminhtml/session');
        if (!is_array($postIds)) {
            $session->addError(Mage::helper('blog')->__('Please select post(s)'));
            $this->_redirect('*/*/index');
            return;
        }
        try {
            foreach ($postIds as $postId) {
                $this->getService()->delete($this->getService()->getById((int) $postId));
            }
            $session->addSuccess(Mage::helper('blog')->__('Total of %d post(s) were deleted', count($postIds)));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/index');
    }

    private function getService(): Maho_Blog_Service_Post
    {
        return Mage::getService('blog/post');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('cms/blog/posts');
    }
}
