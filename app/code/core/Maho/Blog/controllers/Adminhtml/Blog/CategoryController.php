<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Adminhtml_Blog_CategoryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/blog/categories';

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('cms/blog/categories');
        return $this;
    }

    protected function _initCategory(): Maho_Blog_Model_Category
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('blog/category');
        if ($id) {
            $model->load($id);
        }
        Mage::register('blog_category', $model);
        return $model;
    }

    #[Maho\Config\Route('/admin/blog_category/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Blog Categories'));
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/blog_category/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/blog_category/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Blog Category'));

        $id = (int) $this->getRequest()->getParam('id');
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $model = $id ? $this->getService()->getById($id) : $this->getService()->newCategory();
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New Category'));

        $data = $session->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        // The set select posts the form here, so the page shows the fields of the new set with the typed values
        $setId = (int) $this->getRequest()->getParam('set');
        $posted = $this->getRequest()->getPost('category');
        if ($setId && is_array($posted)) {
            $model->addData(array_diff_key($posted, array_flip(['id', 'form_key', 'attribute_set_id'])));
            try {
                $model->setAttributeSetId(Mage::helper('blog')->getValidAttributeSetId($setId, $model->getResource()->getEntityType()));
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        Mage::register('blog_category', $model);

        $label = $id ? Mage::helper('blog')->__('Edit Category') : Mage::helper('blog')->__('New Category');
        $this->_initAction()->_addBreadcrumb($label, $label)->renderLayout();
    }

    #[Maho\Config\Route('/admin/blog_category/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost('category');
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        // The grid serializer posts the post IDs as "3&5&7"
        $postIds = $this->getRequest()->getPost('post_ids');
        if (is_string($postIds)) {
            $data['post_ids'] = Mage::helper('adminhtml/js')->decodeGridSerializedInput($postIds);
        } elseif ($postIds === null) {
            $data['post_ids'] = [];
        }

        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $model = $id ? $this->getService()->getById($id) : $this->getService()->newCategory();
            $this->getService()->save($model->addData($data));
            $session->addSuccess(Mage::helper('blog')->__('Category was successfully saved'));
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
            $session->addError(Mage::helper('blog')->__('Unable to save the category.'));
        }
        $session->setFormData($data);
        $this->_redirect('*/*/edit', ['id' => $id]);
    }

    #[Maho\Config\Route('/admin/blog_category/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $this->getService()->delete($this->getService()->getById($id));
            $session->addSuccess(Mage::helper('blog')->__('Category was successfully deleted'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/blog_category/massDelete')]
    public function massDeleteAction(): void
    {
        $categoryIds = $this->getRequest()->getParam('category');
        $session = Mage::getSingleton('adminhtml/session');
        if (!is_array($categoryIds)) {
            $session->addError(Mage::helper('blog')->__('Please select category(s)'));
            $this->_redirect('*/*/index');
            return;
        }
        $deleted = 0;
        try {
            foreach ($categoryIds as $categoryId) {
                // A category of a deleted subtree is gone already
                try {
                    $category = $this->getService()->getById((int) $categoryId);
                } catch (Mage_Core_Exception_NoSuchEntity) {
                    continue;
                }
                $this->getService()->delete($category);
                $deleted++;
            }
            $session->addSuccess(Mage::helper('blog')->__('Total of %d category(s) were deleted', $deleted));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/index');
    }

    /**
     * AJAX action for Posts tab (initial load with serializer)
     */
    #[Maho\Config\Route('/admin/blog_category/posts')]
    public function postsAction(): void
    {
        $this->_initCategory();
        $this->loadLayout();
        $this->getLayout()->getBlock('blog_category_edit_tab_posts')
            ->setPostsCategory($this->getRequest()->getPost('posts_category'));
        $this->renderLayout();
    }

    /**
     * AJAX action for Posts tab grid (filter/sort/page reload)
     */
    #[Maho\Config\Route('/admin/blog_category/postsGrid')]
    public function postsGridAction(): void
    {
        $this->_initCategory();
        $this->loadLayout();
        $this->getLayout()->getBlock('blog_category_edit_tab_posts')
            ->setPostsCategory($this->getRequest()->getPost('posts_category', []));
        $this->renderLayout();
    }

    private function getService(): Maho_Blog_Service_Category
    {
        return Mage::getService('blog/category');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('cms/blog/categories');
    }
}
