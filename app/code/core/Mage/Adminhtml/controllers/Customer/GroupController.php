<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Customer_GroupController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/group';

    protected function _initGroup()
    {
        $this->_title($this->__('Customers'))->_title($this->__('Customer Groups'));

        Mage::register('current_group', Mage::getModel('customer/group'));
        $groupId = $this->getRequest()->getParam('id');
        if (!is_null($groupId)) {
            Mage::registry('current_group')->load($groupId);
        }
    }

    /**
     * Customer groups list.
     */
    #[Maho\Config\Route('/admin/customer_group/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Customers'))->_title($this->__('Customer Groups'));

        $this->loadLayout();
        $this->_setActiveMenu('customer/group');
        $this->_addBreadcrumb(Mage::helper('customer')->__('Customers'), Mage::helper('customer')->__('Customers'));
        $this->_addBreadcrumb(Mage::helper('customer')->__('Customer Groups'), Mage::helper('customer')->__('Customer Groups'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/customer_group/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Edit or create customer group.
     */
    #[Maho\Config\Route('/admin/customer_group/new')]
    public function newAction(): void
    {
        $this->_initGroup();
        $this->loadLayout();
        $this->_setActiveMenu('customer/group');
        $this->_addBreadcrumb(Mage::helper('customer')->__('Customers'), Mage::helper('customer')->__('Customers'));
        $this->_addBreadcrumb(Mage::helper('customer')->__('Customer Groups'), Mage::helper('customer')->__('Customer Groups'), $this->getUrl('*/customer_group'));

        $currentGroup = Mage::registry('current_group');

        if (!is_null($currentGroup->getId())) {
            $this->_addBreadcrumb(Mage::helper('customer')->__('Edit Group'), Mage::helper('customer')->__('Edit Customer Groups'));
        } else {
            $this->_addBreadcrumb(Mage::helper('customer')->__('New Group'), Mage::helper('customer')->__('New Customer Groups'));
        }

        $this->_title($currentGroup->getId() ? $currentGroup->getCode() : $this->__('New Group'));

        $this->getLayout()->getBlock('content')
            ->append($this->getLayout()->createBlock('adminhtml/customer_group_edit', 'group')
                        ->setEditMode((bool) Mage::registry('current_group')->getId()));

        $this->renderLayout();
    }

    /**
     * Edit customer group action. Forward to new action.
     */
    #[Maho\Config\Route('/admin/customer_group/edit')]
    public function editAction(): void
    {
        $this->_forward('new');
    }

    /**
     * Create or save customer group.
     */
    #[Maho\Config\Route('/admin/customer_group/save')]
    public function saveAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        $group = Mage::getModel('customer/group');
        try {
            if ($id !== null) {
                $group = $this->groupService()->getById((int) $id);
            }
            // The form posts no code for NOT LOGGED IN, because its field is disabled
            $code = $this->getRequest()->getParam('code');
            if ($code !== null) {
                $group->setCode((string) $code);
            }
            $group->setTaxClassId((int) $this->getRequest()->getParam('tax_class') ?: null);
            $this->groupService()->save($group);
            $session->addSuccess(Mage::helper('customer')->__('The customer group has been saved.'));
            $this->_redirect('*/customer_group');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('customer')->__('Unable to save the customer group.'));
        }
        $session->setCustomerGroupData($group->getData());
        $this->_redirect('*/customer_group/edit', ['id' => $id]);
    }

    #[Maho\Config\Route('/admin/customer_group/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('id');
        if (!$id) {
            $this->_redirect('*/customer_group');
            return;
        }
        try {
            $this->groupService()->delete($this->groupService()->getById($id));
            $session->addSuccess(Mage::helper('customer')->__('The customer group has been deleted.'));
            $this->_redirect('*/customer_group');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('customer')->__('Unable to delete the customer group.'));
        }
        $this->_redirect('*/customer_group/edit', ['id' => $id]);
    }

    protected function groupService(): Mage_Customer_Service_Group
    {
        return Mage::getService('customer/group');
    }
}
