<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_System_DesignController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/design';

    #[Maho\Config\Route('/admin/system_design/index')]
    public function indexAction(): void
    {
        $this
            ->_title($this->__('System'))->_title($this->__('Design'))
            ->loadLayout()
            ->_setActiveMenu('system/design')
            ->_addContent($this->getLayout()->createBlock('adminhtml/system_design'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_design/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/system_design_grid')->toHtml());
    }

    #[Maho\Config\Route('/admin/system_design/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/system_design/edit')]
    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $design = $id ? $this->getDesignService()->getById($id) : $this->getDesignService()->newDesignChange();
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $this
            ->_title($this->__('System'))
            ->_title($this->__('Design'))
            ->loadLayout()
            ->_setActiveMenu('system/design');
        $this->_title($design->getId() ? $this->__('Edit Design Change') : $this->__('New Design Change'));

        Mage::register('design', $design);

        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_design_edit'));
        $this->_addLeft($this->getLayout()->createBlock('adminhtml/system_design_edit_tabs', 'design_tabs'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_design/save')]
    public function saveAction(): void
    {
        $post = $this->getRequest()->getPost();
        if (empty($post['design']) || !is_array($post['design'])) {
            $this->_redirect('*/*/');
            return;
        }
        $data = $this->_filterDates($post['design'], ['date_from', 'date_to']);
        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('id');

        try {
            $service = $this->getDesignService();
            $design = $id ? $service->getById($id) : $service->newDesignChange();
            $design->setStoreId(isset($data['store_id']) && $data['store_id'] !== '' ? (int) $data['store_id'] : null)
                ->setDesign(isset($data['design']) ? (string) $data['design'] : null)
                ->setDateFrom(isset($data['date_from']) ? (string) $data['date_from'] : null)
                ->setDateTo(isset($data['date_to']) ? (string) $data['date_to'] : null);
            $service->save($design);
            $session->addSuccess($this->__('The design change has been saved.'));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('Unable to save the design change.'));
        }
        $session->setDesignData($post);
        $this->_redirect('*/*/edit', $id ? ['id' => $id] : []);
    }

    #[Maho\Config\Route('/admin/system_design/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        if ($id = (int) $this->getRequest()->getParam('id')) {
            try {
                $service = $this->getDesignService();
                $service->delete($service->getById($id));
                $session->addSuccess($this->__('The design change has been deleted.'));
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addException($e, $this->__('Cannot delete the design change.'));
            }
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }

    protected function getDesignService(): Mage_Core_Service_Design
    {
        return Mage::getService('core/design');
    }
}
