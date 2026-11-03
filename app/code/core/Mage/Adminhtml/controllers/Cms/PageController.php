<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Cms_PageController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        $this->loadLayout()
            ->_setActiveMenu('cms/page')
            ->_addBreadcrumb(Mage::helper('cms')->__('CMS'), Mage::helper('cms')->__('CMS'))
            ->_addBreadcrumb(Mage::helper('cms')->__('Manage Pages'), Mage::helper('cms')->__('Manage Pages'))
        ;
        return $this;
    }

    /**
     * Index action
     */
    #[Maho\Config\Route('/admin/cms_page/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('CMS'))
             ->_title($this->__('Pages'))
             ->_title($this->__('Manage Content'));

        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_page/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Create new CMS page
     */
    #[Maho\Config\Route('/admin/cms_page/new')]
    public function newAction(): void
    {
        // the same form is used to create and edit
        $this->_forward('edit');
    }

    /**
     * Edit CMS page
     */
    #[Maho\Config\Route('/admin/cms_page/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('CMS'))
             ->_title($this->__('Pages'))
             ->_title($this->__('Manage Content'));

        $id = $this->getRequest()->getParam('page_id');
        $model = Mage::getModel('cms/page');
        if ($id) {
            try {
                $model = $this->pageService()->getById((int) $id);
            } catch (Mage_Core_Exception_NoSuchEntity $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getTitle() : $this->__('New Page'));

        // 3. Set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $data['store_id'] = $data['stores'];
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
        Mage::register('cms_page', $model);

        // 5. Build edit form
        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('cms')->__('Edit Page')
                    : Mage::helper('cms')->__('New Page'),
                $id ? Mage::helper('cms')->__('Edit Page')
                : Mage::helper('cms')->__('New Page'),
            );

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_page/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $data = $this->_filterPostData($data);
        $session = $this->_getSession();
        $id = (int) $this->getRequest()->getParam('page_id');
        $model = Mage::getModel('cms/page');
        try {
            if ($id) {
                $model = $this->pageService()->getById($id);
            }
            $model->setData($data);
            Mage::dispatchEvent('cms_page_prepare_save', ['page' => $model, 'request' => $this->getRequest()]);
            $this->pageService()->save($model);

            $session->addSuccess(Mage::helper('cms')->__('The page has been saved.'));
            $session->setFormData(false);
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['page_id' => $model->getId(), '_current' => true]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('cms')->__('An error occurred while saving the page.'));
        }

        if (empty($data['identifier'])) {
            $data['identifier'] = $model->getIdentifier();
        }
        $session->setFormData($data);
        $this->_redirect('*/*/edit', ['page_id' => $id ?: null]);
    }

    #[Maho\Config\Route('/admin/cms_page/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('page_id');
        if (!$id) {
            $session->addError(Mage::helper('cms')->__('Unable to find a page to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        $title = '';
        try {
            $model = $this->pageService()->getById($id);
            $title = (string) $model->getTitle();
            $this->pageService()->delete($model);
            $session->addSuccess(Mage::helper('cms')->__('The page has been deleted.'));
            Mage::dispatchEvent('adminhtml_cmspage_on_delete', ['title' => $title, 'status' => 'success']);
            $this->_redirect('*/*/');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            Mage::dispatchEvent('adminhtml_cmspage_on_delete', ['title' => $title, 'status' => 'fail']);
            $session->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['page_id' => $id]);
        }
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'new', 'save' => Mage::getSingleton('admin/session')->isAllowed('cms/page/save'),
            'delete' => Mage::getSingleton('admin/session')->isAllowed('cms/page/delete'),
            default => Mage::getSingleton('admin/session')->isAllowed('cms/page'),
        };
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data = $this->_filterDates($data, ['custom_theme_from', 'custom_theme_to']);
        return $data;
    }

    protected function pageService(): Mage_Cms_Service_Page
    {
        return Mage::getService('cms/page');
    }
}
