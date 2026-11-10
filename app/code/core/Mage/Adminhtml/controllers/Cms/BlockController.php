<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Cms_BlockController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/block';

    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        $this->loadLayout()
            ->_setActiveMenu('cms/block')
            ->_addBreadcrumb(Mage::helper('cms')->__('CMS'), Mage::helper('cms')->__('CMS'))
            ->_addBreadcrumb(Mage::helper('cms')->__('Static Blocks'), Mage::helper('cms')->__('Static Blocks'))
        ;
        return $this;
    }

    /**
     * Index action
     */
    #[Maho\Config\Route('/admin/cms_block/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('CMS'))->_title($this->__('Static Blocks'));

        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_block/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Create new CMS block
     */
    #[Maho\Config\Route('/admin/cms_block/new')]
    public function newAction(): void
    {
        // the same form is used to create and edit
        $this->_forward('edit');
    }

    /**
     * Edit CMS block
     */
    #[Maho\Config\Route('/admin/cms_block/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('CMS'))->_title($this->__('Static Blocks'));

        $id = $this->getRequest()->getParam('block_id');
        $model = $this->blockService()->newBlock();
        if ($id) {
            try {
                $model = $this->blockService()->getById((int) $id);
            } catch (Mage_Core_Exception_NoSuchEntity $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getTitle() : $this->__('New Block'));

        // 3. Set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
        Mage::register('cms_block', $model);

        // 5. Build edit form
        $this->_initAction()
            ->_addBreadcrumb($id ? Mage::helper('cms')->__('Edit Block') : Mage::helper('cms')->__('New Block'), $id ? Mage::helper('cms')->__('Edit Block') : Mage::helper('cms')->__('New Block'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/cms_block/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('block_id');
        try {
            $model = $id ? $this->blockService()->getById($id) : Mage::getModel('cms/block');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        try {
            $this->blockService()->save($model->setData($data));
            $session->addSuccess(Mage::helper('cms')->__('The block has been saved.'));
            $session->setFormData(false);
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['block_id' => $model->getId()]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Exception $e) {
            $session->addError($e->getMessage());
        }
        $session->setFormData($data);
        $this->_redirect('*/*/edit', ['block_id' => $id ?: null]);
    }

    #[Maho\Config\Route('/admin/cms_block/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('block_id');
        if (!$id) {
            $session->addError(Mage::helper('cms')->__('Unable to find a block to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $this->blockService()->delete($this->blockService()->getById($id));
            $session->addSuccess(Mage::helper('cms')->__('The block has been deleted.'));
            $this->_redirect('*/*/');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['block_id' => $id]);
        }
    }

    protected function blockService(): Mage_Cms_Service_Block
    {
        return Mage::getService('cms/block');
    }
}
