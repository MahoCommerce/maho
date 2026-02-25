<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Adminhtml_Blog_CategoryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/blog/categories';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['save', 'delete', 'massDelete']);
        return parent::preDispatch();
    }

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

    public function indexAction(): void
    {
        $this->_title($this->__('Blog Categories'));
        $this->_initAction();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $this->_title($this->__('Blog Category'));

        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('blog/category');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('blog')->__('This category no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New Category'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('blog_category', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('blog')->__('Edit Category') : Mage::helper('blog')->__('New Category'),
                $id ? Mage::helper('blog')->__('Edit Category') : Mage::helper('blog')->__('New Category'),
            )
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost('category')) {
            $model = Mage::getModel('blog/category');
            $id = $this->getRequest()->getParam('id');

            if ($id) {
                $model->load($id);
            }

            // Process post_ids from grid serializer (simplified format: "3&5&7")
            $postIds = $this->getRequest()->getPost('post_ids');
            if (is_string($postIds)) {
                $data['post_ids'] = Mage::helper('adminhtml/js')->decodeGridSerializedInput($postIds);
            } elseif (!isset($postIds)) {
                $data['post_ids'] = [];
            }

            try {
                $model->addData($data)
                    ->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Category was successfully saved'),
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
                $model = Mage::getModel('blog/category');
                $model->load($id);

                // Delete all descendant categories first
                Mage::getResourceSingleton('blog/category')->deleteDescendants((int) $model->getId());
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Category was successfully deleted'),
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
        $categoryIds = $this->getRequest()->getParam('category');
        if (!is_array($categoryIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('blog')->__('Please select category(s)'),
            );
        } else {
            try {
                foreach ($categoryIds as $categoryId) {
                    $category = Mage::getModel('blog/category')->load($categoryId);
                    Mage::getResourceSingleton('blog/category')->deleteDescendants((int) $category->getId());
                    $category->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('blog')->__('Total of %d category(s) were deleted', count($categoryIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * AJAX action for Posts tab (initial load with serializer)
     */
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
    public function postsGridAction(): void
    {
        $this->_initCategory();
        $this->loadLayout();
        $this->getLayout()->getBlock('blog_category_edit_tab_posts')
            ->setPostsCategory($this->getRequest()->getPost('posts_category', []));
        $this->renderLayout();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('cms/blog/categories');
    }
}
