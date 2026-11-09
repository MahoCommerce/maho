<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Permissions_UserController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/acl/users';

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('system/acl/users')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('Permissions'), $this->__('Permissions'))
            ->_addBreadcrumb($this->__('Users'), $this->__('Users'))
        ;
        return $this;
    }

    #[Maho\Config\Route('/admin/permissions_user/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
             ->_title($this->__('Permissions'))
             ->_title($this->__('Users'));

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('adminhtml/permissions_user'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/permissions_user/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/permissions_user/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('System'))
             ->_title($this->__('Permissions'))
             ->_title($this->__('Users'));

        $id = $this->getRequest()->getParam('user_id');
        try {
            $model = $id ? $this->getService()->getById((int) $id) : Mage::getModel('admin/user');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New User'));

        // Restore previously entered form data from session
        $data = Mage::getSingleton('adminhtml/session')->getUserData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('permissions_user', $model);

        if (isset($id)) {
            $breadcrumb = $this->__('Edit User');
        } else {
            $breadcrumb = $this->__('New User');
        }
        $this->_initAction()
            ->_addBreadcrumb($breadcrumb, $breadcrumb);

        $this->getLayout()->getBlock('adminhtml.permissions.user.edit')
            ->setData('action', $this->getUrl('*/permissions_user/save'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/permissions_user/save')]
    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('user_id');
        try {
            $model = $id ? $this->getService()->getById((int) $id) : Mage::getModel('admin/user');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $currentPassword = $this->getRequest()->getParam('current_password');
        $this->getRequest()->setParam('current_password', null);
        unset($data['current_password']);
        $result = $this->_validateCurrentPassword($currentPassword);

        $model->setData($data);
        // A blank password field keeps the current password
        if ($model->hasNewPassword() && $model->getNewPassword() === '') {
            $model->unsNewPassword();
        }
        if ($model->hasPasswordConfirmation() && $model->getPasswordConfirmation() === '') {
            $model->unsPasswordConfirmation();
        }

        $errors = is_array($result) ? $result : [];
        if ($errors === []) {
            $roles = $this->getRequest()->getParam('roles');
            try {
                // The role grid sends a list, and a user has one role
                $this->getService()->save(
                    $model,
                    is_array($roles) && $roles !== [] ? (int) reset($roles) : null,
                    (int) Mage::getSingleton('admin/session')->getUser()->getId(),
                );
                $session->addSuccess($this->__('The user has been saved.'));
                $session->setUserData(false);
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception $e) {
                $errors = explode("\n", $e->getMessage());
            }
        }
        foreach ($errors as $message) {
            $session->addError($message);
        }
        $session->setUserData($data);
        $this->_redirect('*/*/edit', ['_current' => true]);
    }

    #[Maho\Config\Route('/admin/permissions_user/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('user_id');

        $currentPassword = $this->getRequest()->getParam('current_password');
        $this->getRequest()->setParam('current_password', null);
        $result = $this->_validateCurrentPassword($currentPassword);
        if (is_array($result)) {
            foreach ($result as $error) {
                $session->addError($error);
            }
            $this->_redirect('*/*/edit', ['user_id' => $id]);
            return;
        }

        if (!$id) {
            $session->addError($this->__('Unable to find a user to delete.'));
            $this->_redirect('*/*/');
            return;
        }
        if (Mage::getSingleton('admin/session')->getUser()->getId() == $id) {
            $session->addError($this->__('You cannot delete your own account.'));
            $this->_redirect('*/*/edit', ['user_id' => $id]);
            return;
        }

        try {
            $this->getService()->delete($this->getService()->getById($id));
            $session->addSuccess($this->__('The user has been deleted.'));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('An error occurred while deleting this user.'));
        }
        $this->_redirect('*/*/edit', ['user_id' => $id]);
    }

    #[Maho\Config\Route('/admin/permissions_user/rolesGrid')]
    public function rolesGridAction(): void
    {
        $id = $this->getRequest()->getParam('user_id');
        $model = Mage::getModel('admin/user');

        if ($id) {
            $model->load($id);
        }

        Mage::register('permissions_user', $model);
        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('adminhtml/permissions_user_edit_tab_roles')
                ->toHtml(),
        );
    }

    #[Maho\Config\Route('/admin/permissions_user/roleGrid')]
    public function roleGridAction(): void
    {
        $this->getResponse()
            ->setBody($this->getLayout()
            ->createBlock('adminhtml/permissions_user_grid')
            ->toHtml());
    }

    protected function getService(): Mage_Admin_Service_User
    {
        /** @var Mage_Admin_Service_User */
        return Mage::getService('admin/user');
    }
}
